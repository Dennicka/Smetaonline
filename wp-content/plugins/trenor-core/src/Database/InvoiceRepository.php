<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\InvoiceVersionProvider;
use Trenor\Core\Domain\Service\InvoiceStatusAccess;
use Trenor\Core\Domain\Service\DocumentFinanceTransitionPolicy;

final class InvoiceRepository extends BaseRepository implements InvoiceVersionProvider, InvoiceStatusAccess
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_invoices';
    }

    protected function entityType(): string
    {
        return 'invoice';
    }

    /** @return array<int, array<string, mixed>> */
    public function byOffert(int $offertId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE offert_id = %d ORDER BY version_no DESC, id DESC", $offertId), ARRAY_A) ?: [];
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'offert_id' => (int) ($data['offert_id'] ?? 0),
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'document_number' => sanitize_text_field((string) ($data['document_number'] ?? '')),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => sanitize_key((string) ($data['status'] ?? 'issued')),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'tax_mode' => sanitize_key((string) ($data['tax_mode'] ?? 'private_consumer')),
            'reverse_charge_note' => sanitize_text_field((string) ($data['reverse_charge_note'] ?? '')),
            'client_company_name' => sanitize_text_field((string) ($data['client_company_name'] ?? '')),
            'client_org_number' => sanitize_text_field((string) ($data['client_org_number'] ?? '')),
            'client_vat_number' => sanitize_text_field((string) ($data['client_vat_number'] ?? '')),
            'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
            'labour_total_minor' => (int) ($data['labour_total_minor'] ?? 0),
            'materials_total_minor' => (int) ($data['materials_total_minor'] ?? 0),
            'subtotal_ex_vat_minor' => (int) ($data['subtotal_ex_vat_minor'] ?? 0),
            'vat_minor' => (int) ($data['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($data['total_inc_vat_minor'] ?? 0),
            'rot_requested' => ! empty($data['rot_requested']) ? 1 : 0,
            'housing_type' => sanitize_key((string) ($data['housing_type'] ?? '')),
            'rot_eligibility_status' => sanitize_key((string) ($data['rot_eligibility_status'] ?? 'not_requested')),
            'rot_ineligibility_reason' => sanitize_key((string) ($data['rot_ineligibility_reason'] ?? '')),
            'rot_eligible_labour_minor' => (int) ($data['rot_eligible_labour_minor'] ?? 0),
            'preliminary_rot_minor' => (int) ($data['preliminary_rot_minor'] ?? 0),
            'total_after_preliminary_rot_minor' => (int) ($data['total_after_preliminary_rot_minor'] ?? ($data['total_inc_vat_minor'] ?? 0)),
            'rot_buyer_count' => (int) ($data['rot_buyer_count'] ?? 0),
            'rot_buyers_json' => (string) ($data['rot_buyers_json'] ?? '[]'),
            'rot_allocation_json' => (string) ($data['rot_allocation_json'] ?? '[]'),
            'rot_property_reference' => sanitize_text_field((string) ($data['rot_property_reference'] ?? '')),
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
            'offert_id' => (int) ($data['offert_id'] ?? 0),
            'estimate_id' => (int) ($data['estimate_id'] ?? 0),
            'document_number' => (string) ($data['document_number'] ?? ''),
            'version_no' => (int) ($data['version_no'] ?? 1),
            'status' => (string) ($data['status'] ?? 'issued'),
        ]);

        return $id;
    }

    public function nextVersionNo(int $offertId): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        $max = $wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM {$this->table()} WHERE offert_id = %d", $offertId));

        return ((int) $max) + 1;
    }

    public function transitionStatus(int $id, string $status): bool
    {
        global $wpdb;

        $invoice = $this->find($id);
        if (! is_array($invoice)) {
            return false;
        }

        $currentStatus = sanitize_key((string) ($invoice['status'] ?? ''));
        $normalizedStatus = sanitize_key($status);
        if (! (new DocumentFinanceTransitionPolicy())->canTransitionInvoiceStatus($currentStatus, $normalizedStatus)) {
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
