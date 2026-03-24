<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class InvoiceLedgerSummaryBuilder
{
    private const STATUS_BUCKETS = ['issued', 'partially_paid', 'paid', 'archived'];

    /**
     * @param array<int, array<string, mixed>> $invoiceRows
     * @param array<int, array<string, mixed>> $paymentRows
     * @return array<string, int>
     */
    public function build(array $invoiceRows, array $paymentRows): array
    {
        $summary = [
            'invoice_count' => 0,
            'issued_count' => 0,
            'partially_paid_count' => 0,
            'paid_count' => 0,
            'archived_count' => 0,
            'total_invoiced_minor' => 0,
            'total_paid_minor' => 0,
            'total_outstanding_minor' => 0,
        ];

        if ($invoiceRows === []) {
            return $summary;
        }

        $paidByInvoiceId = $this->paidByInvoiceId($paymentRows);

        foreach ($invoiceRows as $invoice) {
            $invoiceId = $this->toInt($invoice['id'] ?? null);
            $invoiceTotalMinor = max($this->toInt($invoice['total_inc_vat_minor'] ?? null), 0);
            $paidTotalMinor = max($paidByInvoiceId[$invoiceId] ?? 0, 0);
            $outstandingMinor = max($invoiceTotalMinor - $paidTotalMinor, 0);

            $summary['invoice_count']++;
            $summary['total_invoiced_minor'] += $invoiceTotalMinor;
            $summary['total_paid_minor'] += $paidTotalMinor;
            $summary['total_outstanding_minor'] += $outstandingMinor;

            $status = strtolower((string) ($invoice['status'] ?? ''));
            if (in_array($status, self::STATUS_BUCKETS, true)) {
                $summary[$status . '_count']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $paymentRows
     * @return array<int, int>
     */
    private function paidByInvoiceId(array $paymentRows): array
    {
        $totals = [];

        foreach ($paymentRows as $payment) {
            $invoiceId = $this->toInt($payment['invoice_id'] ?? null);
            if ($invoiceId <= 0) {
                continue;
            }

            $totals[$invoiceId] = ($totals[$invoiceId] ?? 0) + max($this->toInt($payment['amount_minor'] ?? null), 0);
        }

        return $totals;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) round((float) $value);
        }

        return 0;
    }
}
