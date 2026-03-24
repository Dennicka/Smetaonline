<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;

final class ProjectDossierBuilder
{
    private InvoicePaymentSummaryCalculator $paymentSummaryCalculator;

    public function __construct(InvoicePaymentSummaryCalculator $paymentSummaryCalculator)
    {
        $this->paymentSummaryCalculator = $paymentSummaryCalculator;
    }

    /**
     * @param array<string, mixed> $project
     * @param array<string, mixed> $property
     * @param array<string, mixed> $client
     * @param array<int, array<string, mixed>> $estimates
     * @param array<int, array<string, mixed>> $offerts
     * @param array<int, array<string, mixed>> $invoices
     * @param array<int, array<int, array<string, mixed>>> $paymentsByInvoiceId
     * @return array<string, mixed>
     */
    public function build(
        array $project,
        array $property,
        array $client,
        array $estimates,
        array $offerts,
        array $invoices,
        array $paymentsByInvoiceId
    ): array {
        $estimateIds = [];
        foreach ($estimates as $estimate) {
            $estimateIds[] = $this->toInt($estimate['id'] ?? null);
        }

        $estimateIdSet = array_fill_keys(array_filter($estimateIds, static fn (int $id): bool => $id > 0), true);

        $filteredOfferts = [];
        $offertIds = [];
        foreach ($offerts as $offert) {
            $estimateId = $this->toInt($offert['estimate_id'] ?? null);
            if ($estimateId > 0 && isset($estimateIdSet[$estimateId])) {
                $filteredOfferts[] = $offert;
                $offertIds[] = $this->toInt($offert['id'] ?? null);
            }
        }

        $offertIdSet = array_fill_keys(array_filter($offertIds, static fn (int $id): bool => $id > 0), true);

        $filteredInvoices = [];
        $invoiceIds = [];
        foreach ($invoices as $invoice) {
            $offertId = $this->toInt($invoice['offert_id'] ?? null);
            $estimateId = $this->toInt($invoice['estimate_id'] ?? null);
            if (($offertId > 0 && isset($offertIdSet[$offertId])) || ($estimateId > 0 && isset($estimateIdSet[$estimateId]))) {
                $filteredInvoices[] = $invoice;
                $invoiceIds[] = $this->toInt($invoice['id'] ?? null);
            }
        }

        $invoiceIdSet = array_fill_keys(array_filter($invoiceIds, static fn (int $id): bool => $id > 0), true);

        $invoiceRows = [];
        $filteredPayments = [];
        $summary = [
            'estimates_count' => count($estimates),
            'offerts_count' => count($filteredOfferts),
            'invoices_count' => count($filteredInvoices),
            'payments_count' => 0,
            'invoiced_total_minor' => 0,
            'paid_total_minor' => 0,
            'outstanding_total_minor' => 0,
            'fully_paid_invoices_count' => 0,
            'partially_paid_invoices_count' => 0,
            'archived_invoices_count' => 0,
        ];

        foreach ($filteredInvoices as $invoice) {
            $invoiceId = $this->toInt($invoice['id'] ?? null);
            $payments = $invoiceId > 0 && isset($invoiceIdSet[$invoiceId]) && isset($paymentsByInvoiceId[$invoiceId]) && is_array($paymentsByInvoiceId[$invoiceId])
                ? $paymentsByInvoiceId[$invoiceId]
                : [];
            $paymentSummary = $this->paymentSummaryCalculator->calculate($invoice, $payments);

            $invoiceRow = $invoice;
            $invoiceRow['paid_total_minor'] = max($this->toInt($paymentSummary['paid_total_minor'] ?? null), 0);
            $invoiceRow['outstanding_minor'] = max($this->toInt($paymentSummary['outstanding_minor'] ?? null), 0);
            $invoiceRow['payment_count'] = max($this->toInt($paymentSummary['payment_count'] ?? null), 0);
            $invoiceRow['computed_status'] = (string) ($paymentSummary['computed_status'] ?? '');
            $invoiceRows[] = $invoiceRow;

            foreach ($payments as $payment) {
                if (! is_array($payment)) {
                    continue;
                }

                $paymentInvoiceId = $this->toInt($payment['invoice_id'] ?? null);
                if ($paymentInvoiceId > 0 && isset($invoiceIdSet[$paymentInvoiceId])) {
                    $filteredPayments[] = $payment;
                }
            }

            $summary['invoiced_total_minor'] += max($this->toInt($invoice['total_inc_vat_minor'] ?? null), 0);
            $summary['paid_total_minor'] += $invoiceRow['paid_total_minor'];
            $summary['outstanding_total_minor'] += $invoiceRow['outstanding_minor'];

            if (strtolower((string) $invoiceRow['computed_status']) === 'paid') {
                $summary['fully_paid_invoices_count']++;
            }

            if (strtolower((string) $invoiceRow['computed_status']) === 'partially_paid') {
                $summary['partially_paid_invoices_count']++;
            }

            if (strtolower((string) ($invoice['status'] ?? '')) === 'archived') {
                $summary['archived_invoices_count']++;
            }
        }

        $summary['payments_count'] = count($filteredPayments);

        return [
            'project' => $project,
            'property' => $property,
            'client' => $client,
            'estimates' => $estimates,
            'offerts' => $filteredOfferts,
            'invoices' => $invoiceRows,
            'payments' => $filteredPayments,
            'summary' => $summary,
        ];
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
