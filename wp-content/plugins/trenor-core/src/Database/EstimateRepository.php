<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class EstimateRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_estimates';
    }

    protected function entityType(): string
    {
        return 'estimate';
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'status' => sanitize_key((string) ($data['status'] ?? 'draft')),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'tax_mode' => sanitize_key((string) ($data['tax_mode'] ?? 'private_consumer')),
            'reverse_charge_note' => sanitize_text_field((string) ($data['reverse_charge_note'] ?? '')),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'labour_rate_minor' => (int) ($data['labour_rate_minor'] ?? 0),
            'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'rot_requested' => ! empty($data['rot_requested']) ? 1 : 0,
            'housing_type' => sanitize_key((string) ($data['housing_type'] ?? '')),
            'rot_is_new_build' => ! empty($data['rot_is_new_build']) ? 1 : 0,
            'rot_property_reference' => sanitize_text_field((string) ($data['rot_property_reference'] ?? '')),
            'rot_buyers_json' => (string) ($data['rot_buyers_json'] ?? '[]'),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s']);
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
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'status' => sanitize_key((string) ($data['status'] ?? 'draft')),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'tax_mode' => sanitize_key((string) ($data['tax_mode'] ?? 'private_consumer')),
            'reverse_charge_note' => sanitize_text_field((string) ($data['reverse_charge_note'] ?? '')),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'labour_rate_minor' => (int) ($data['labour_rate_minor'] ?? 0),
            'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'rot_requested' => ! empty($data['rot_requested']) ? 1 : 0,
            'housing_type' => sanitize_key((string) ($data['housing_type'] ?? '')),
            'rot_is_new_build' => ! empty($data['rot_is_new_build']) ? 1 : 0,
            'rot_property_reference' => sanitize_text_field((string) ($data['rot_property_reference'] ?? '')),
            'rot_buyers_json' => (string) ($data['rot_buyers_json'] ?? '[]'),
            'updated_at' => current_time('mysql', true),
        ];

        if (array_key_exists('calculated_at', $data)) {
            $payload['calculated_at'] = $data['calculated_at'];
        }

        $updated = $wpdb->update($this->table(), $payload, ['id' => $id]);
        if ($updated === false) {
            return false;
        }
        if ($updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
        }
        return true;
    }
}
