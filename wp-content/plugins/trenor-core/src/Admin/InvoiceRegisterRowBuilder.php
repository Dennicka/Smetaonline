<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;

final class InvoiceRegisterRowBuilder
{
    private InvoicePaymentSummaryCalculator $calculator;

    public function __construct(InvoicePaymentSummaryCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<int, array<string, mixed>> $payments
     * @return array<string, int|string>
     */
    public function build(array $invoice, array $payments): array
    {
        $paymentSummary = $this->calculator->calculate($invoice, $payments);

        return [
            'id' => $this->normalizeScalar($invoice['id'] ?? null),
            'offert_id' => $this->normalizeScalar($invoice['offert_id'] ?? null),
            'estimate_id' => $this->normalizeScalar($invoice['estimate_id'] ?? null),
            'document_number' => $this->normalizeScalar($invoice['document_number'] ?? null),
            'version_no' => $this->normalizeScalar($invoice['version_no'] ?? null),
            'stored_status' => $this->normalizeScalar($invoice['status'] ?? null),
            'computed_status' => $this->normalizeScalar($paymentSummary['computed_status'] ?? null),
            'total_inc_vat_minor' => max($this->toInt($invoice['total_inc_vat_minor'] ?? null), 0),
            'paid_total_minor' => max($this->toInt($paymentSummary['paid_total_minor'] ?? null), 0),
            'outstanding_minor' => max($this->toInt($paymentSummary['outstanding_minor'] ?? null), 0),
            'payment_count' => max($this->toInt($paymentSummary['payment_count'] ?? null), 0),
            'issued_at' => $this->normalizeScalar($invoice['issued_at'] ?? null),
            'currency' => $this->normalizeScalar($invoice['currency'] ?? 'SEK'),
        ];
    }

    private function normalizeScalar(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return (string) $value;
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
