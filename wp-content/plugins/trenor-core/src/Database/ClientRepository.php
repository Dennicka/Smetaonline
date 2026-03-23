<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class ClientRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_clients';
    }

    protected function entityType(): string
    {
        return 'client';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'org_number' => sanitize_text_field((string) ($data['org_number'] ?? '')),
                'email' => sanitize_email((string) ($data['email'] ?? '')),
                'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
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
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'org_number' => sanitize_text_field((string) ($data['org_number'] ?? '')),
                'email' => sanitize_email((string) ($data['email'] ?? '')),
                'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s'],
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
