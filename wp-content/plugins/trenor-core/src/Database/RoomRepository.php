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
                'room_type' => sanitize_key((string) ($data['room_type'] ?? 'generic')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'condition_state' => sanitize_key((string) ($data['condition_state'] ?? 'unknown')),
                'length_m' => (float) ($data['length_m'] ?? 0),
                'width_m' => (float) ($data['width_m'] ?? 0),
                'height_m' => (float) ($data['height_m'] ?? 0),
                'area_m2' => (float) ($data['area_m2'] ?? 0),
                'window_count' => (int) ($data['window_count'] ?? 0),
                'door_count' => (int) ($data['door_count'] ?? 0),
                'work_context' => sanitize_text_field((string) ($data['work_context'] ?? '')),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%d', '%s', '%s', '%s', '%s']
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
                'room_type' => sanitize_key((string) ($data['room_type'] ?? 'generic')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'condition_state' => sanitize_key((string) ($data['condition_state'] ?? 'unknown')),
                'length_m' => (float) ($data['length_m'] ?? 0),
                'width_m' => (float) ($data['width_m'] ?? 0),
                'height_m' => (float) ($data['height_m'] ?? 0),
                'area_m2' => (float) ($data['area_m2'] ?? 0),
                'window_count' => (int) ($data['window_count'] ?? 0),
                'door_count' => (int) ($data['door_count'] ?? 0),
                'work_context' => sanitize_text_field((string) ($data['work_context'] ?? '')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%d', '%s', '%s'],
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
    public function byProject(int $projectId): array
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM {$this->table()} WHERE project_id = %d ORDER BY id DESC", $projectId);

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }
}
