<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Domain\Service\InvoicePaymentAccess;

final class InvoicePaymentRepository extends BaseRepository implements InvoicePaymentAccess
{
    protected function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_invoice_payments';
    }

    protected function entityType(): string
    {
        return 'invoice_payment';
    }

    public function create(array $data): ?int
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'payment_date' => sanitize_text_field((string) ($data['payment_date'] ?? $now)),
            'amount_minor' => (int) ($data['amount_minor'] ?? 0),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'method' => sanitize_text_field((string) ($data['method'] ?? 'manual')),
            'reference' => sanitize_text_field((string) ($data['reference'] ?? '')),
            'note' => sanitize_text_field((string) ($data['note'] ?? '')),
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
            'amount_minor' => (int) ($data['amount_minor'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? 'SEK')),
            'method' => (string) ($data['method'] ?? 'manual'),
        ]);

        return $id;
    }

    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE invoice_id = %d ORDER BY payment_date ASC, id ASC", $invoiceId), ARRAY_A) ?: [];
    }

    public function totalPaidByInvoice(int $invoiceId): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
        $sum = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount_minor) FROM {$this->table()} WHERE invoice_id = %d", $invoiceId));

        return (int) $sum;
    }
}
