<?php

declare(strict_types=1);

namespace Trenor\Core\Backup;

use Trenor\Core\Database\AuditLogger;

final class BackupManifestRepository
{
    private AuditLogger $auditLogger;

    public function __construct(?AuditLogger $auditLogger = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_backup_manifests';
    }

    public function create(string $backupType, int $createdByUserId, array $manifest): int
    {
        global $wpdb;

        $createdAt = current_time('mysql', true);
        $ok = $wpdb->insert(
            $this->table(),
            [
                'backup_type' => sanitize_key($backupType),
                'status' => 'started',
                'created_at' => $createdAt,
                'created_by_user_id' => $createdByUserId > 0 ? $createdByUserId : null,
                'manifest_json' => wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'db_snapshot_path' => '',
                'artifact_bundle_path' => null,
                'checksum_sha256' => '',
                'error_message' => null,
                'completed_at' => null,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($ok === false || (int) $wpdb->insert_id <= 0) {
            throw new \RuntimeException('Unable to create backup manifest.');
        }

        $manifestId = (int) $wpdb->insert_id;
        $this->auditLogger->log('backup_manifest', $manifestId, 'create', [
            'backup_type' => $backupType,
            'status' => 'started',
        ]);

        return $manifestId;
    }

    public function complete(int $id, string $dbSnapshotPath, ?string $artifactBundlePath, string $checksumSha256, array $manifest): void
    {
        global $wpdb;

        $ok = $wpdb->update(
            $this->table(),
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql', true),
                'manifest_json' => wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'db_snapshot_path' => $dbSnapshotPath,
                'artifact_bundle_path' => $artifactBundlePath,
                'checksum_sha256' => $checksumSha256,
                'error_message' => null,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($ok === false) {
            throw new \RuntimeException('Unable to complete backup manifest.');
        }

        $this->auditLogger->log('backup_manifest', $id, 'complete', [
            'db_snapshot_path' => $dbSnapshotPath,
            'artifact_bundle_path' => $artifactBundlePath,
            'checksum_sha256' => $checksumSha256,
        ]);
    }

    public function fail(int $id, string $errorMessage, array $manifest): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table(),
            [
                'status' => 'failed',
                'completed_at' => current_time('mysql', true),
                'manifest_json' => wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'error_message' => sanitize_text_field($errorMessage),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $this->auditLogger->log('backup_manifest', $id, 'fail', [
            'error_message' => $errorMessage,
        ]);
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal prefixed table name.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function latest(int $limit = 30): array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal prefixed table name.
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        ) ?: [];
    }
}
