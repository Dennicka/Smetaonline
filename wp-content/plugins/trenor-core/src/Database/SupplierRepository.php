<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class SupplierRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_suppliers';
    }

    protected function entityType(): string
    {
        return 'supplier';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'code' => sanitize_key((string) ($data['code'] ?? '')),
                'source_type' => sanitize_text_field((string) ($data['source_type'] ?? 'catalog')),
                'country' => strtoupper(sanitize_text_field((string) ($data['country'] ?? 'SE'))),
                'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
                'is_active' => ! empty($data['is_active']) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
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

        $updated = $wpdb->update(
            $this->table(),
            [
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'code' => sanitize_key((string) ($data['code'] ?? '')),
                'source_type' => sanitize_text_field((string) ($data['source_type'] ?? 'catalog')),
                'country' => strtoupper(sanitize_text_field((string) ($data['country'] ?? 'SE'))),
                'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
                'is_active' => ! empty($data['is_active']) ? 1 : 0,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s'],
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

    public function archive(int $id): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'is_active' => 0,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated === false || $updated === 0) {
            return false;
        }

        $this->auditLogger->log($this->entityType(), $id, 'archive', []);

        return true;
    }
}
