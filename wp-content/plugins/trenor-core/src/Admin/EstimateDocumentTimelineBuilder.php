<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class EstimateDocumentTimelineBuilder
{
    /**
     * @param array<int, array<string, mixed>> $snapshots
     * @param array<int, array<string, mixed>> $offerts
     * @param array<int, array<int, array<string, mixed>>> $invoicesByOffertId
     * @param array<int, array<int, array<string, mixed>>> $paymentsByInvoiceId
     * @return array{summary: array<string, int>, rows: array<int, array<string, string|int>>}
     */
    public function build(
        int $estimateId,
        array $snapshots,
        array $offerts,
        array $invoicesByOffertId,
        array $paymentsByInvoiceId
    ): array {
        $summary = [
            'snapshots_count' => 0,
            'offerts_count' => 0,
            'invoices_count' => 0,
            'payments_count' => 0,
            'invoiced_total_minor' => 0,
            'paid_total_minor' => 0,
            'outstanding_minor' => 0,
        ];
        $rows = [];

        foreach ($snapshots as $snapshot) {
            $snapshotId = $this->toInt($snapshot['id'] ?? '');
            $summary['snapshots_count']++;
            $rows[] = [
                'source_type' => 'snapshot',
                'source_id' => $snapshotId > 0 ? $snapshotId : '',
                'estimate_id' => $estimateId,
                'offert_id' => '',
                'invoice_id' => '',
                'document_number_or_reference' => (string) ($snapshot['snapshot_type'] ?? ''),
                'status' => '',
                'event_at' => (string) ($snapshot['created_at'] ?? ''),
                'amount_minor' => '',
                'currency' => '',
                'action_target' => $snapshotId > 0
                    ? 'admin.php?page=trn_estimates&estimate_id=' . $estimateId . '&snapshot_id=' . $snapshotId
                    : '',
            ];
        }

        foreach ($offerts as $offert) {
            $offertId = $this->toInt($offert['id'] ?? '');
            $offertEstimateId = $this->toInt($offert['estimate_id'] ?? '') ?: $estimateId;
            $summary['offerts_count']++;
            $rows[] = [
                'source_type' => 'offert',
                'source_id' => $offertId > 0 ? $offertId : '',
                'estimate_id' => $offertEstimateId,
                'offert_id' => $offertId > 0 ? $offertId : '',
                'invoice_id' => '',
                'document_number_or_reference' => (string) ($offert['document_number'] ?? ''),
                'status' => (string) ($offert['status'] ?? ''),
                'event_at' => (string) ($offert['issued_at'] ?? $offert['created_at'] ?? ''),
                'amount_minor' => (string) ($offert['total_inc_vat_minor'] ?? ''),
                'currency' => (string) ($offert['currency'] ?? ''),
                'action_target' => $offertId > 0 ? 'admin.php?page=trn_offerts&offert_id=' . $offertId : '',
            ];

            $invoices = $invoicesByOffertId[$offertId] ?? [];
            foreach ($invoices as $invoice) {
                $invoiceId = $this->toInt($invoice['id'] ?? '');
                $invoiceEstimateId = $this->toInt($invoice['estimate_id'] ?? '') ?: $offertEstimateId;
                $invoiceOffertId = $this->toInt($invoice['offert_id'] ?? '') ?: $offertId;
                $invoiceTotalMinor = max($this->toInt($invoice['total_inc_vat_minor'] ?? ''), 0);
                $summary['invoices_count']++;
                $summary['invoiced_total_minor'] += $invoiceTotalMinor;
                $rows[] = [
                    'source_type' => 'invoice',
                    'source_id' => $invoiceId > 0 ? $invoiceId : '',
                    'estimate_id' => $invoiceEstimateId > 0 ? $invoiceEstimateId : $estimateId,
                    'offert_id' => $invoiceOffertId > 0 ? $invoiceOffertId : '',
                    'invoice_id' => $invoiceId > 0 ? $invoiceId : '',
                    'document_number_or_reference' => (string) ($invoice['document_number'] ?? ''),
                    'status' => (string) ($invoice['status'] ?? ''),
                    'event_at' => (string) ($invoice['issued_at'] ?? $invoice['created_at'] ?? ''),
                    'amount_minor' => (string) ($invoice['total_inc_vat_minor'] ?? ''),
                    'currency' => (string) ($invoice['currency'] ?? ''),
                    'action_target' => $invoiceId > 0 ? 'admin.php?page=trn_invoices&invoice_id=' . $invoiceId : '',
                ];

                $payments = $paymentsByInvoiceId[$invoiceId] ?? [];
                foreach ($payments as $payment) {
                    $paymentId = $this->toInt($payment['id'] ?? '');
                    $paymentInvoiceId = $this->toInt($payment['invoice_id'] ?? '') ?: $invoiceId;
                    $paidMinor = max($this->toInt($payment['amount_minor'] ?? ''), 0);
                    $summary['payments_count']++;
                    $summary['paid_total_minor'] += $paidMinor;
                    $rows[] = [
                        'source_type' => 'payment',
                        'source_id' => $paymentId > 0 ? $paymentId : '',
                        'estimate_id' => $invoiceEstimateId > 0 ? $invoiceEstimateId : $estimateId,
                        'offert_id' => $invoiceOffertId > 0 ? $invoiceOffertId : '',
                        'invoice_id' => $paymentInvoiceId > 0 ? $paymentInvoiceId : '',
                        'document_number_or_reference' => (string) ($payment['payment_reference'] ?? $invoice['document_number'] ?? ''),
                        'status' => (string) ($payment['status'] ?? ''),
                        'event_at' => (string) ($payment['payment_date'] ?? $payment['created_at'] ?? ''),
                        'amount_minor' => (string) ($payment['amount_minor'] ?? ''),
                        'currency' => (string) ($payment['currency'] ?? $invoice['currency'] ?? ''),
                        'action_target' => $paymentInvoiceId > 0
                            ? 'admin.php?page=trn_invoices&invoice_id=' . $paymentInvoiceId
                            : '',
                    ];
                }
            }
        }

        $summary['outstanding_minor'] = max($summary['invoiced_total_minor'] - $summary['paid_total_minor'], 0);
        usort($rows, [$this, 'compareRows']);

        return ['summary' => $summary, 'rows' => $rows];
    }

    /** @param array<string, string|int> $left @param array<string, string|int> $right */
    private function compareRows(array $left, array $right): int
    {
        $leftEventAt = (string) ($left['event_at'] ?? '');
        $rightEventAt = (string) ($right['event_at'] ?? '');
        $leftHasDate = $leftEventAt !== '';
        $rightHasDate = $rightEventAt !== '';

        if ($leftHasDate && ! $rightHasDate) {
            return -1;
        }
        if (! $leftHasDate && $rightHasDate) {
            return 1;
        }
        if ($leftHasDate && $rightHasDate && $leftEventAt !== $rightEventAt) {
            return strcmp($rightEventAt, $leftEventAt);
        }

        $leftType = (string) ($left['source_type'] ?? '');
        $rightType = (string) ($right['source_type'] ?? '');
        if ($leftType !== $rightType) {
            return strcmp($leftType, $rightType);
        }

        return $this->toInt($right['source_id'] ?? '') <=> $this->toInt($left['source_id'] ?? '');
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
