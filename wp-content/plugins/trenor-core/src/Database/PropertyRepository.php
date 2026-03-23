<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class PropertyRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_properties';
    }

    protected function entityType(): string
    {
        return 'property';
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert(
            $this->table(),
            [
                'client_id' => (int) ($data['client_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'address_line' => sanitize_text_field((string) ($data['address_line'] ?? '')),
                'city' => sanitize_text_field((string) ($data['city'] ?? '')),
                'postal_code' => sanitize_text_field((string) ($data['postal_code'] ?? '')),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
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
                'client_id' => (int) ($data['client_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'address_line' => sanitize_text_field((string) ($data['address_line'] ?? '')),
                'city' => sanitize_text_field((string) ($data['city'] ?? '')),
                'postal_code' => sanitize_text_field((string) ($data['postal_code'] ?? '')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
            return true;
        }

        return false;
    }
}
