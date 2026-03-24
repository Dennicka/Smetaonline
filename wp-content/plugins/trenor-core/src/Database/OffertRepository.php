<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\OffertVersionProvider;

final class OffertRepository extends BaseRepository implements OffertVersionProvider
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_offerts';
    }

    protected function entityType(): string
    {
        return 'offert';
    }

    /** @return array<int, array<string, mixed>> */
    public function byEstimate(int $estimateId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE estimate_id = %d ORDER BY version_no DESC, id DESC", $estimateId), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'document_number' => sanitize_text_field((string) ($data['document_number'] ?? '')),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => sanitize_key((string) ($data['status'] ?? 'issued')),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'labour_total_minor' => (int) ($data['labour_total_minor'] ?? 0),
            'materials_total_minor' => (int) ($data['materials_total_minor'] ?? 0),
            'subtotal_ex_vat_minor' => (int) ($data['subtotal_ex_vat_minor'] ?? 0),
            'vat_minor' => (int) ($data['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($data['total_inc_vat_minor'] ?? 0),
            'snapshot_json' => (string) ($data['snapshot_json'] ?? ''),
            'issued_at' => (string) ($data['issued_at'] ?? $now),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($ok === false) {
            return null;
        }

        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            return null;
        }

        $this->auditLogger->log($this->entityType(), $id, 'create', [
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'document_number' => (string) ($data['document_number'] ?? ''),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => (string) ($data['status'] ?? 'issued'),
        ]);

        return $id;
    }

    public function transitionStatus(int $id, string $status): bool
    {
        global $wpdb;

        $allowed = ['issued', 'accepted', 'rejected', 'archived'];
        $nextStatus = sanitize_key($status);
        if (! in_array($nextStatus, $allowed, true)) {
            return false;
        }

        $updated = $wpdb->update(
            $this->table(),
            [
                'status' => $nextStatus,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false || $updated === 0) {
            return false;
        }

        $this->auditLogger->log($this->entityType(), $id, 'status_transition', ['status' => $nextStatus]);

        return true;
    }

    public function nextVersionNo(int $estimateId): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        $max = $wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM {$this->table()} WHERE estimate_id = %d", $estimateId));
        $current = (int) $max;

        return $current + 1;
    }
}
