<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\CreditNoteVersionProvider;
use Trenor\Core\Domain\Service\DocumentFinanceTransitionPolicy;

final class CreditNoteRepository extends BaseRepository implements CreditNoteVersionProvider
{
    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_credit_notes';
    }

    protected function entityType(): string
    {
        return 'credit_note';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'offert_id' => (int) ($data['offert_id'] ?? 0),
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
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'document_number' => (string) ($data['document_number'] ?? ''),
            'version_no' => (int) ($data['version_no'] ?? 1),
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

        $creditNote = $this->find($id);
        if (! is_array($creditNote)) {
            return false;
        }

        $currentStatus = sanitize_key((string) ($creditNote['status'] ?? ''));
        $normalizedStatus = sanitize_key($status);
        if (! (new DocumentFinanceTransitionPolicy())->canTransitionCreditNoteStatus($currentStatus, $normalizedStatus)) {
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
