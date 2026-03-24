<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class EstimateLineRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_estimate_lines';
    }

    protected function entityType(): string
    {
        return 'estimate_line';
    }

    /** @return array<int, array<string, mixed>> */
    public function byEstimate(int $estimateId): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE estimate_id = %d AND status = %s ORDER BY sort_order, id", $estimateId, 'active'), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'room_id' => ! empty($data['room_id']) ? (int) $data['room_id'] : null,
            'work_item_id' => ! empty($data['work_item_id']) ? (int) $data['work_item_id'] : null,
            'line_title_ru_snapshot' => sanitize_text_field((string) ($data['line_title_ru_snapshot'] ?? '')),
            'line_title_sv_snapshot' => sanitize_text_field((string) ($data['line_title_sv_snapshot'] ?? '')),
            'unit_code_snapshot' => sanitize_text_field((string) ($data['unit_code_snapshot'] ?? '')),
            'quantity' => (float) ($data['quantity'] ?? 0),
            'speed_profile' => sanitize_key((string) ($data['speed_profile'] ?? 'medium')),
            'norm_per_hour_snapshot' => (float) ($data['norm_per_hour_snapshot'] ?? 0),
            'complexity_coeff' => (float) ($data['complexity_coeff'] ?? 1),
            'surface_coeff' => (float) ($data['surface_coeff'] ?? 1),
            'access_coeff' => (float) ($data['access_coeff'] ?? 1),
            'urgency_coeff' => (float) ($data['urgency_coeff'] ?? 1),
            'manual_hours_delta' => (float) ($data['manual_hours_delta'] ?? 0),
            'calculated_hours' => (float) ($data['calculated_hours'] ?? 0),
            'labour_rate_minor_snapshot' => (int) ($data['labour_rate_minor_snapshot'] ?? 0),
            'labour_subtotal_minor' => (int) ($data['labour_subtotal_minor'] ?? 0),
            'status' => 'active',
            'archived_at' => null,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
        $payload = $data;
        $payload['updated_at'] = current_time('mysql', true);
        $updated = $wpdb->update($this->table(), $payload, ['id' => $id]);
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
        $archivedAt = current_time('mysql', true);
        $updated = $wpdb->update(
            $this->table(),
            [
                'status' => 'archived',
                'archived_at' => $archivedAt,
                'updated_at' => $archivedAt,
            ],
            [
                'id' => $id,
                'status' => 'active',
            ],
            ['%s', '%s', '%s'],
            ['%d', '%s']
        );
        if ($updated === false || $updated === 0) {
            return false;
        }
        $this->auditLogger->log($this->entityType(), $id, 'archive', ['status' => 'archived', 'archived_at' => $archivedAt]);
        return true;
    }
}
