<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class DocumentArtifactRepository
{
    private AuditLogger $auditLogger;

    public function __construct(?AuditLogger $auditLogger = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_document_artifacts';
    }

    public function findByDocumentVersion(string $documentType, int $documentId, int $versionNo, string $artifactType = 'pdf'): ?array
    {
        global $wpdb;

        $table = $this->table();
        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internally constructed with WP prefix.
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE document_type = %s AND document_id = %d AND version_no = %d AND artifact_type = %s LIMIT 1",
                sanitize_key($documentType),
                $documentId,
                $versionNo,
                sanitize_key($artifactType)
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert(
            $this->table(),
            [
                'document_type' => sanitize_key((string) ($data['document_type'] ?? '')),
                'document_id' => (int) ($data['document_id'] ?? 0),
                'version_no' => (int) ($data['version_no'] ?? 1),
                'artifact_type' => sanitize_key((string) ($data['artifact_type'] ?? 'pdf')),
                'storage_path' => sanitize_text_field((string) ($data['storage_path'] ?? '')),
                'mime_type' => sanitize_text_field((string) ($data['mime_type'] ?? 'application/pdf')),
                'checksum_sha256' => sanitize_text_field((string) ($data['checksum_sha256'] ?? '')),
                'created_at' => (string) ($data['created_at'] ?? $now),
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($ok === false) {
            return null;
        }

        $artifactId = (int) $wpdb->insert_id;
        if ($artifactId <= 0) {
            return null;
        }

        $this->auditLogger->log('document_artifact', $artifactId, 'create', [
            'document_type' => (string) ($data['document_type'] ?? ''),
            'document_id' => (int) ($data['document_id'] ?? 0),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'artifact_type' => (string) ($data['artifact_type'] ?? 'pdf'),
            'storage_path' => (string) ($data['storage_path'] ?? ''),
        ]);

        return $artifactId;
    }
}
