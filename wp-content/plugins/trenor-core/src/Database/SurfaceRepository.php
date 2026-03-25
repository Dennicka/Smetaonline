<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class SurfaceRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_surfaces';
    }

    protected function entityType(): string
    {
        return 'surface';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'room_id' => (int) ($data['room_id'] ?? 0),
                'surface_type' => sanitize_key((string) ($data['surface_type'] ?? 'wall')),
                'length_m' => (float) ($data['length_m'] ?? 0),
                'width_m' => (float) ($data['width_m'] ?? 0),
                'height_m' => (float) ($data['height_m'] ?? 0),
                'area_m2' => (float) ($data['area_m2'] ?? 0),
                'condition_state' => sanitize_key((string) ($data['condition_state'] ?? 'unknown')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s']
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
                'room_id' => (int) ($data['room_id'] ?? 0),
                'surface_type' => sanitize_key((string) ($data['surface_type'] ?? 'wall')),
                'length_m' => (float) ($data['length_m'] ?? 0),
                'width_m' => (float) ($data['width_m'] ?? 0),
                'height_m' => (float) ($data['height_m'] ?? 0),
                'area_m2' => (float) ($data['area_m2'] ?? 0),
                'condition_state' => sanitize_key((string) ($data['condition_state'] ?? 'unknown')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s'],
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

    /** @return array<int, array<string,mixed>> */
    public function byRoom(int $roomId): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE room_id = %d ORDER BY id DESC", $roomId),
            ARRAY_A
        ) ?: [];
    }
}
