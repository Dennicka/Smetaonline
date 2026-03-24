<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class EstimateMaterialLineRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_estimate_material_lines';
    }

    protected function entityType(): string
    {
        return 'estimate_material_line';
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
            'estimate_line_id' => ! empty($data['estimate_line_id']) ? (int) $data['estimate_line_id'] : null,
            'material_id' => ! empty($data['material_id']) ? (int) $data['material_id'] : null,
            'material_name_ru_snapshot' => sanitize_text_field((string) ($data['material_name_ru_snapshot'] ?? '')),
            'material_name_sv_snapshot' => sanitize_text_field((string) ($data['material_name_sv_snapshot'] ?? '')),
            'unit_code_snapshot' => sanitize_text_field((string) ($data['unit_code_snapshot'] ?? '')),
            'quantity' => (float) ($data['quantity'] ?? 0),
            'coverage_snapshot' => (float) ($data['coverage_snapshot'] ?? 0),
            'buy_price_minor_snapshot' => (int) ($data['buy_price_minor_snapshot'] ?? 0),
            'sell_price_minor_snapshot' => (int) ($data['sell_price_minor_snapshot'] ?? 0),
            'subtotal_minor' => (int) ($data['subtotal_minor'] ?? 0),
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
