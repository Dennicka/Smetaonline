<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class AttachmentRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_attachments';
    }

    protected function entityType(): string
    {
        return 'attachment';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'parent_entity_type' => sanitize_key((string) ($data['parent_entity_type'] ?? '')),
                'parent_entity_id' => (int) ($data['parent_entity_id'] ?? 0),
                'file_url' => esc_url_raw((string) ($data['file_url'] ?? '')),
                'file_path' => sanitize_text_field((string) ($data['file_path'] ?? '')),
                'original_filename' => sanitize_text_field((string) ($data['original_filename'] ?? '')),
                'mime_type' => sanitize_text_field((string) ($data['mime_type'] ?? '')),
                'title' => sanitize_text_field((string) ($data['title'] ?? '')),
                'caption' => sanitize_text_field((string) ($data['caption'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'uploaded_by' => (int) ($data['uploaded_by'] ?? 0),
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
                'created_at' => $now,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            return null;
        }

        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            return null;
        }

        $this->auditLogger->log($this->entityType(), $id, 'create', $data);

        return $id;
    }

    public function updateEntity(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'title' => sanitize_text_field((string) ($data['title'] ?? '')),
                'caption' => sanitize_text_field((string) ($data['caption'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        if ($updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
        }

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function byParent(string $entityType, int $entityId): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE parent_entity_type = %s AND parent_entity_id = %d ORDER BY id DESC",
            sanitize_key($entityType),
            $entityId
        );

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }
}
