<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class WorkCategoryRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_work_categories';
    }

    protected function entityType(): string
    {
        return 'work_category';
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'name_ru' => sanitize_text_field((string) ($data['name_ru'] ?? '')),
            'name_sv' => sanitize_text_field((string) ($data['name_sv'] ?? '')),
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'status' => sanitize_key((string) ($data['status'] ?? 'active')),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%d', '%s', '%s', '%s']);
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
            'name_ru' => sanitize_text_field((string) ($data['name_ru'] ?? '')),
            'name_sv' => sanitize_text_field((string) ($data['name_sv'] ?? '')),
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'status' => sanitize_key((string) ($data['status'] ?? 'active')),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%s', '%s', '%d', '%s', '%s'], ['%d']);
        if ($updated === false) {
            return false;
        }
        if ($updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
        }
        return true;
    }
}
