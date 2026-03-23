<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class WorkItemRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_work_items';
    }

    protected function entityType(): string
    {
        return 'work_item';
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'name_ru' => sanitize_text_field((string) ($data['name_ru'] ?? '')),
            'name_sv' => sanitize_text_field((string) ($data['name_sv'] ?? '')),
            'unit_code' => sanitize_text_field((string) ($data['unit_code'] ?? '')),
            'norm_slow_per_hour' => (float) ($data['norm_slow_per_hour'] ?? 0),
            'norm_medium_per_hour' => (float) ($data['norm_medium_per_hour'] ?? 0),
            'norm_fast_per_hour' => (float) ($data['norm_fast_per_hour'] ?? 0),
            'default_material_consumption_note' => sanitize_text_field((string) ($data['default_material_consumption_note'] ?? '')),
            'is_rot_eligible' => ! empty($data['is_rot_eligible']) ? 1 : 0,
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%d', '%s', '%s']);
        if ($ok === false) {
            return null;
        }
        $id = (int) $wpdb->insert_id;
        if ($id > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'create', $data);
            return $id;
        }
        return null;
    }

    public function updateEntity(int $id, array $data): bool
    {
        global $wpdb;
        $updated = $wpdb->update($this->table(), [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'name_ru' => sanitize_text_field((string) ($data['name_ru'] ?? '')),
            'name_sv' => sanitize_text_field((string) ($data['name_sv'] ?? '')),
            'unit_code' => sanitize_text_field((string) ($data['unit_code'] ?? '')),
            'norm_slow_per_hour' => (float) ($data['norm_slow_per_hour'] ?? 0),
            'norm_medium_per_hour' => (float) ($data['norm_medium_per_hour'] ?? 0),
            'norm_fast_per_hour' => (float) ($data['norm_fast_per_hour'] ?? 0),
            'default_material_consumption_note' => sanitize_text_field((string) ($data['default_material_consumption_note'] ?? '')),
            'is_rot_eligible' => ! empty($data['is_rot_eligible']) ? 1 : 0,
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%d', '%s'], ['%d']);
        if ($updated === false) {
            return false;
        }
        if ($updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
        }
        return true;
    }

    public function archive(int $id): bool
    {
        global $wpdb;
        $updated = $wpdb->update($this->table(), ['is_active' => 0, 'updated_at' => current_time('mysql', true)], ['id' => $id], ['%d', '%s'], ['%d']);
        if ($updated === false || $updated === 0) {
            return false;
        }
        $this->auditLogger->log($this->entityType(), $id, 'archive', []);
        return true;
    }
}
