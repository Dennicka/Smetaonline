<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class ProjectRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_projects';
    }

    protected function entityType(): string
    {
        return 'project';
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert(
            $this->table(),
            [
                'property_id' => (int) ($data['property_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'code' => sanitize_text_field((string) ($data['code'] ?? '')),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        $id = (int) $wpdb->insert_id;
        $this->auditLogger->log($this->entityType(), $id, 'create', $data);

        return $id;
    }

    public function updateEntity(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'property_id' => (int) ($data['property_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'code' => sanitize_text_field((string) ($data['code'] ?? '')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
            return true;
        }

        return false;
    }
}
