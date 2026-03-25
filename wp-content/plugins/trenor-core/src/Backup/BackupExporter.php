<?php

declare(strict_types=1);

namespace Trenor\Core\Backup;

final class BackupExporter
{
    /** @var array<int, string> */
    private const TABLE_SUFFIXES = [
        'trn_schema_migrations',
        'trn_audit_log',
        'trn_clients',
        'trn_properties',
        'trn_projects',
        'trn_rooms',
        'trn_document_sequences',
        'trn_work_categories',
        'trn_work_items',
        'trn_material_categories',
        'trn_materials',
        'trn_estimates',
        'trn_estimate_lines',
        'trn_estimate_material_lines',
        'trn_offerts',
        'trn_estimate_snapshots',
        'trn_invoices',
        'trn_invoice_payments',
        'trn_credit_notes',
        'trn_operation_tokens',
        'trn_operation_receipts',
        'trn_document_artifacts',
        'trn_avtals',
        'trn_reminders',
        'trn_atas',
        'trn_suppliers',
        'trn_price_import_batches',
        'trn_material_supplier_prices',
        'trn_backup_manifests',
    ];

    private BackupManifestRepository $manifestRepository;

    public function __construct(?BackupManifestRepository $manifestRepository = null)
    {
        $this->manifestRepository = $manifestRepository ?? new BackupManifestRepository();
    }

    /** @return array{manifest_id:int, db_snapshot_path:string, artifact_bundle_path:?string, checksum_sha256:string} */
    public function create(int $createdByUserId): array
    {
        $manifest = [
            'schema_version' => 'backup_restore_v1',
            'started_at' => current_time('mysql', true),
            'tables' => [],
            'artifacts' => ['count' => 0, 'files' => []],
        ];

        $manifestId = $this->manifestRepository->create('plugin_owned_v1', $createdByUserId, $manifest);
        $backupRoot = $this->backupRoot();
        $bundleDir = $backupRoot . '/backup-' . $manifestId;
        $artifactDir = $bundleDir . '/artifacts';
        $dbSnapshotPath = $bundleDir . '/db-snapshot.json';

        try {
            $this->ensureDirectory($artifactDir);
            $dbPayload = $this->buildDatabaseSnapshotPayload();
            $this->writeJsonFile($dbSnapshotPath, $dbPayload);

            $artifactPayload = $this->captureArtifacts($artifactDir);
            $artifactManifestPath = $bundleDir . '/artifact-manifest.json';
            $this->writeJsonFile($artifactManifestPath, $artifactPayload);

            $checksum = hash('sha256', hash_file('sha256', $dbSnapshotPath) . '|' . hash_file('sha256', $artifactManifestPath));
            $manifest['completed_at'] = current_time('mysql', true);
            $manifest['tables'] = $dbPayload['tables'];
            $manifest['artifacts'] = [
                'count' => (int) ($artifactPayload['count'] ?? 0),
                'manifest_path' => $artifactManifestPath,
                'files' => $artifactPayload['files'] ?? [],
            ];

            $this->manifestRepository->complete($manifestId, $dbSnapshotPath, $artifactDir, $checksum, $manifest);

            return [
                'manifest_id' => $manifestId,
                'db_snapshot_path' => $dbSnapshotPath,
                'artifact_bundle_path' => $artifactDir,
                'checksum_sha256' => $checksum,
            ];
        } catch (\Throwable $exception) {
            $manifest['failed_at'] = current_time('mysql', true);
            $manifest['error'] = $exception->getMessage();
            $this->manifestRepository->fail($manifestId, $exception->getMessage(), $manifest);

            throw new \RuntimeException('Backup failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /** @return array{schema_version:string,taken_at:string,tables:array<int,array<string,mixed>>} */
    private function buildDatabaseSnapshotPayload(): array
    {
        global $wpdb;

        $payload = [
            'schema_version' => 'backup_restore_v1',
            'taken_at' => current_time('mysql', true),
            'tables' => [],
        ];

        foreach (self::TABLE_SUFFIXES as $suffix) {
            $tableName = $wpdb->prefix . $suffix;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
            if ($exists === null || $exists === '') {
                throw new \RuntimeException('Critical plugin table is missing from backup source: ' . $tableName);
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal prefixed table name and deterministic ordering by id.
            $rows = $wpdb->get_results("SELECT * FROM {$tableName} ORDER BY id ASC", ARRAY_A) ?: [];
            $rowHash = hash('sha256', wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $payload['tables'][] = [
                'name' => $tableName,
                'suffix' => $suffix,
                'row_count' => count($rows),
                'rows_sha256' => $rowHash,
                'rows' => $rows,
            ];
        }

        return $payload;
    }

    /** @return array{count:int,files:array<int,array<string,mixed>>} */
    private function captureArtifacts(string $artifactDir): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'trn_document_artifacts';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal prefixed table name and deterministic ordering by id.
        $rows = $wpdb->get_results("SELECT * FROM {$tableName} ORDER BY id ASC", ARRAY_A) ?: [];
        $files = [];

        foreach ($rows as $row) {
            $sourcePath = (string) ($row['storage_path'] ?? '');
            if ($sourcePath === '' || ! is_readable($sourcePath)) {
                throw new \RuntimeException('Artifact payload is missing or unreadable for artifact_id=' . (int) ($row['id'] ?? 0));
            }

            $artifactId = (int) ($row['id'] ?? 0);
            $targetFileName = $artifactId . '-' . basename($sourcePath);
            $targetPath = $artifactDir . '/' . $targetFileName;

            if (! copy($sourcePath, $targetPath)) {
                throw new \RuntimeException('Failed to copy artifact payload for artifact_id=' . $artifactId);
            }

            $files[] = [
                'artifact_id' => $artifactId,
                'document_type' => (string) ($row['document_type'] ?? ''),
                'document_id' => (int) ($row['document_id'] ?? 0),
                'version_no' => (int) ($row['version_no'] ?? 0),
                'bundle_path' => $targetPath,
                'storage_path' => $sourcePath,
                'checksum_sha256' => hash_file('sha256', $targetPath),
            ];
        }

        return [
            'count' => count($files),
            'files' => $files,
        ];
    }

    private function backupRoot(): string
    {
        $uploadDir = wp_upload_dir();
        $baseDir = is_array($uploadDir) ? (string) ($uploadDir['basedir'] ?? '') : '';
        if ($baseDir === '') {
            throw new \RuntimeException('Upload directory is not configured.');
        }

        $root = $baseDir . '/trenor-backups';
        $this->ensureDirectory($root);

        return $root;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! wp_mkdir_p($path)) {
            throw new \RuntimeException('Unable to create backup directory: ' . $path);
        }
    }

    private function writeJsonFile(string $path, array $payload): void
    {
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || $json === '') {
            throw new \RuntimeException('Unable to serialize backup payload as JSON.');
        }

        $bytes = file_put_contents($path, $json);
        if ($bytes === false || $bytes <= 0) {
            throw new \RuntimeException('Unable to write backup file: ' . $path);
        }
    }
}
