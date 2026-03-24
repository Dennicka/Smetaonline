<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\DocumentFinanceTransitionPolicy;
use Trenor\Core\Domain\Service\ReminderVersionProvider;

final class ReminderRepository extends BaseRepository implements ReminderVersionProvider
{
    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_reminders';
    }

    protected function entityType(): string
    {
        return 'reminder';
    }

    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE invoice_id = %d ORDER BY version_no DESC, id DESC", $invoiceId), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'offert_id' => (int) ($data['offert_id'] ?? 0),
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'project_id' => (int) ($data['project_id'] ?? 0),
            'client_id' => (int) ($data['client_id'] ?? 0),
            'document_number' => sanitize_text_field((string) ($data['document_number'] ?? '')),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => sanitize_key((string) ($data['status'] ?? 'issued')),
            'reminder_level' => max(1, (int) ($data['reminder_level'] ?? 1)),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'labour_total_minor' => (int) ($data['labour_total_minor'] ?? 0),
            'materials_total_minor' => (int) ($data['materials_total_minor'] ?? 0),
            'subtotal_ex_vat_minor' => (int) ($data['subtotal_ex_vat_minor'] ?? 0),
            'vat_minor' => (int) ($data['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($data['total_inc_vat_minor'] ?? 0),
            'snapshot_json' => (string) ($data['snapshot_json'] ?? ''),
            'issued_at' => (string) ($data['issued_at'] ?? $now),
            'actor_user_id' => isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
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
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'document_number' => (string) ($data['document_number'] ?? ''),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'reminder_level' => max(1, (int) ($data['reminder_level'] ?? 1)),
            'status' => (string) ($data['status'] ?? 'issued'),
        ]);

        return $id;
    }

    public function nextVersionNo(int $invoiceId): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        $max = $wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM {$this->table()} WHERE invoice_id = %d", $invoiceId));

        return ((int) $max) + 1;
    }

    public function transitionStatus(int $id, string $status): bool
    {
        global $wpdb;

        $reminder = $this->find($id);
        if (! is_array($reminder)) {
            return false;
        }

        $currentStatus = sanitize_key((string) ($reminder['status'] ?? ''));
        $normalizedStatus = sanitize_key($status);
        if (! (new DocumentFinanceTransitionPolicy())->canTransitionReminderStatus($currentStatus, $normalizedStatus)) {
            return false;
        }

        $updated = $wpdb->update(
            $this->table(),
            [
                'status' => $normalizedStatus,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false && $updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'transition_status', ['status' => $normalizedStatus]);

            return true;
        }

        return false;
    }
}
