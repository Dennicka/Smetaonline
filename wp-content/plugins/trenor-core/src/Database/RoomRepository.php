<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class RoomRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_rooms';
    }

    protected function entityType(): string
    {
        return 'room';
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'project_id' => (int) ($data['project_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'floor' => sanitize_text_field((string) ($data['floor'] ?? '')),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
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
                'project_id' => (int) ($data['project_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'floor' => sanitize_text_field((string) ($data['floor'] ?? '')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s'],
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
}
