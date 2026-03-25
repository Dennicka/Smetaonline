<?php

declare(strict_types=1);

namespace Trenor\Core\Backup;

use Trenor\Core\Database\AuditLogger;

final class BackupRestorer
{
    private BackupManifestRepository $manifestRepository;

    private AuditLogger $auditLogger;

    public function __construct(?BackupManifestRepository $manifestRepository = null, ?AuditLogger $auditLogger = null)
    {
        $this->manifestRepository = $manifestRepository ?? new BackupManifestRepository();
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

    public function restore(int $manifestId, string $confirmation): void
    {
        if ($manifestId <= 0) {
            throw new \RuntimeException('Backup manifest id must be a positive integer.');
        }

        if ($confirmation !== ('RESTORE ' . $manifestId)) {
            throw new \RuntimeException('Restore confirmation phrase is invalid.');
        }

        $manifest = $this->manifestRepository->find($manifestId);
        if (! is_array($manifest)) {
            throw new \RuntimeException('Backup manifest was not found.');
        }

        if ((string) ($manifest['status'] ?? '') !== 'completed') {
            throw new \RuntimeException('Backup manifest is not restorable: status must be completed.');
        }

        $dbSnapshotPath = (string) ($manifest['db_snapshot_path'] ?? '');
        $artifactBundlePath = (string) ($manifest['artifact_bundle_path'] ?? '');
        $storedChecksum = (string) ($manifest['checksum_sha256'] ?? '');

        if ($dbSnapshotPath === '' || ! is_readable($dbSnapshotPath)) {
            throw new \RuntimeException('Backup DB snapshot is missing or unreadable.');
        }

        if ($artifactBundlePath === '' || ! is_dir($artifactBundlePath)) {
            throw new \RuntimeException('Backup artifact bundle is missing or unreadable.');
        }

        $artifactManifestPath = dirname($artifactBundlePath) . '/artifact-manifest.json';
        if (! is_readable($artifactManifestPath)) {
            throw new \RuntimeException('Backup artifact manifest is missing or unreadable.');
        }

        $computedChecksum = hash('sha256', hash_file('sha256', $dbSnapshotPath) . '|' . hash_file('sha256', $artifactManifestPath));
        if (! hash_equals($storedChecksum, $computedChecksum)) {
            throw new \RuntimeException('Backup checksum mismatch. Restore aborted.');
        }

        $dbPayload = $this->decodeJsonFile($dbSnapshotPath, 'DB snapshot');
        $artifactPayload = $this->decodeJsonFile($artifactManifestPath, 'artifact manifest');

        $this->validatePayload($dbPayload, $artifactPayload);

        $this->auditLogger->log('backup_manifest', $manifestId, 'restore_started', [
            'manifest_id' => $manifestId,
        ]);

        $this->restoreDatabase($dbPayload);
        $this->restoreArtifacts($artifactPayload);

        $this->auditLogger->log('backup_manifest', $manifestId, 'restore_completed', [
            'manifest_id' => $manifestId,
        ]);
    }

    /** @return array<string, mixed> */
    private function decodeJsonFile(string $path, string $label): array
    {
        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            throw new \RuntimeException(sprintf('Backup %s file is empty.', $label));
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException(sprintf('Backup %s file is invalid JSON.', $label));
        }

        return $decoded;
    }

    /** @param array<string,mixed> $dbPayload @param array<string,mixed> $artifactPayload */
    private function validatePayload(array $dbPayload, array $artifactPayload): void
    {
        $tables = $dbPayload['tables'] ?? null;
        if (! is_array($tables) || $tables === []) {
            throw new \RuntimeException('Backup DB snapshot does not contain required table payload.');
        }

        $files = $artifactPayload['files'] ?? null;
        if (! is_array($files)) {
            throw new \RuntimeException('Backup artifact payload is invalid.');
        }

        foreach ($files as $file) {
            if (! is_array($file)) {
                throw new \RuntimeException('Backup artifact payload has invalid record shape.');
            }

            $bundlePath = (string) ($file['bundle_path'] ?? '');
            $checksum = (string) ($file['checksum_sha256'] ?? '');

            if ($bundlePath === '' || ! is_readable($bundlePath)) {
                throw new \RuntimeException('Backup artifact file is missing: ' . $bundlePath);
            }

            if (! hash_equals($checksum, (string) hash_file('sha256', $bundlePath))) {
                throw new \RuntimeException('Backup artifact checksum mismatch for: ' . $bundlePath);
            }
        }
    }

    /** @param array<string,mixed> $dbPayload */
    private function restoreDatabase(array $dbPayload): void
    {
        global $wpdb;

        $tables = is_array($dbPayload['tables'] ?? null) ? $dbPayload['tables'] : [];
        if ($tables === []) {
            throw new \RuntimeException('Restore payload has no tables.');
        }

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($tables as $tablePayload) {
                if (! is_array($tablePayload)) {
                    throw new \RuntimeException('Restore table payload is invalid.');
                }

                $tableName = (string) ($tablePayload['name'] ?? '');
                $rows = is_array($tablePayload['rows'] ?? null) ? $tablePayload['rows'] : null;

                if ($tableName === '' || $rows === null) {
                    throw new \RuntimeException('Restore table payload is incomplete.');
                }

                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
                if ($exists === null || $exists === '') {
                    throw new \RuntimeException('Restore destination table is missing: ' . $tableName);
                }

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal prefixed table name.
                $wpdb->query("DELETE FROM {$tableName}");

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        throw new \RuntimeException('Restore row payload is invalid for table: ' . $tableName);
                    }

                    $ok = $wpdb->insert($tableName, $row);
                    if ($ok === false) {
                        throw new \RuntimeException('Restore insert failed for table: ' . $tableName);
                    }
                }
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw new \RuntimeException('Database restore failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<string,mixed> $artifactPayload */
    private function restoreArtifacts(array $artifactPayload): void
    {
        $files = is_array($artifactPayload['files'] ?? null) ? $artifactPayload['files'] : [];

        foreach ($files as $file) {
            if (! is_array($file)) {
                throw new \RuntimeException('Artifact restore payload is invalid.');
            }

            $source = (string) ($file['bundle_path'] ?? '');
            $target = (string) ($file['storage_path'] ?? '');
            if ($source === '' || $target === '') {
                throw new \RuntimeException('Artifact restore payload is incomplete.');
            }

            $targetDir = dirname($target);
            if (! is_dir($targetDir) && ! wp_mkdir_p($targetDir)) {
                throw new \RuntimeException('Unable to create artifact target directory: ' . $targetDir);
            }

            if (! copy($source, $target)) {
                throw new \RuntimeException('Unable to restore artifact file to: ' . $target);
            }
        }
    }
}
