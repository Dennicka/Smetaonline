<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class InvoicePaymentSummaryCalculator
{
    /**
     * @param array<string, mixed> $invoice
     * @param array<int, array<string, mixed>> $payments
     * @return array<string, int|string>
     */
    public function calculate(array $invoice, array $payments): array
    {
        $invoiceTotalMinor = (int) ($invoice['total_inc_vat_minor'] ?? 0);
        $paidTotalMinor = 0;

        foreach ($payments as $payment) {
            $paidTotalMinor += (int) ($payment['amount_minor'] ?? 0);
        }

        $outstandingMinor = max($invoiceTotalMinor - $paidTotalMinor, 0);

        $computedStatus = 'issued';
        if ($paidTotalMinor > 0 && $paidTotalMinor < $invoiceTotalMinor) {
            $computedStatus = 'partially_paid';
        }

        if ($paidTotalMinor === $invoiceTotalMinor) {
            $computedStatus = 'paid';
        }

        return [
            'invoice_total_minor' => $invoiceTotalMinor,
            'paid_total_minor' => $paidTotalMinor,
            'outstanding_minor' => $outstandingMinor,
            'payment_count' => count($payments),
            'computed_status' => $computedStatus,
        ];
    }
}
