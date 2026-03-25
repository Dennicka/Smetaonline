<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class ContactPersonRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_contact_persons';
    }

    protected function entityType(): string
    {
        return 'contact_person';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'client_id' => (int) ($data['client_id'] ?? 0),
                'property_id' => (int) ($data['property_id'] ?? 0),
                'project_id' => (int) ($data['project_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'role_title' => sanitize_text_field((string) ($data['role_title'] ?? '')),
                'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
                'email' => sanitize_email((string) ($data['email'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'is_primary' => ! empty($data['is_primary']) ? 1 : 0,
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
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
                'client_id' => (int) ($data['client_id'] ?? 0),
                'property_id' => (int) ($data['property_id'] ?? 0),
                'project_id' => (int) ($data['project_id'] ?? 0),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'role_title' => sanitize_text_field((string) ($data['role_title'] ?? '')),
                'phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
                'email' => sanitize_email((string) ($data['email'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'is_primary' => ! empty($data['is_primary']) ? 1 : 0,
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'],
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
        return $this->byForeignKey('project_id', $projectId);
    }

    /** @return array<int, array<string, mixed>> */
    public function byProperty(int $propertyId): array
    {
        return $this->byForeignKey('property_id', $propertyId);
    }

    /** @return array<int, array<string, mixed>> */
    public function byClient(int $clientId): array
    {
        return $this->byForeignKey('client_id', $clientId);
    }

    /** @return array<int, array<string, mixed>> */
    private function byForeignKey(string $column, int $value): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column name is fixed by internal calls.
        $query = $wpdb->prepare("SELECT * FROM {$this->table()} WHERE {$column} = %d ORDER BY is_primary DESC, id DESC", $value);

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }
}
