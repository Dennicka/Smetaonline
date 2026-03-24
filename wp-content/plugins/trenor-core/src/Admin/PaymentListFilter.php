<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class PaymentListFilter
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $rawFilters
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $rows, array $rawFilters): array
    {
        $paymentId = $this->normalizePositiveInteger($rawFilters['payment_id'] ?? null);
        $invoiceId = $this->normalizePositiveInteger($rawFilters['invoice_id'] ?? null);
        $currency = $this->normalizeScalarText($rawFilters['currency'] ?? null);
        $method = $this->normalizeScalarText($rawFilters['method'] ?? null);
        $reference = $this->normalizeScalarText($rawFilters['reference'] ?? null);
        $paymentDateFrom = $this->normalizeDate($rawFilters['payment_date_from'] ?? null);
        $paymentDateTo = $this->normalizeDate($rawFilters['payment_date_to'] ?? null);

        if (
            $paymentId === null
            && $invoiceId === null
            && $currency === null
            && $method === null
            && $reference === null
            && $paymentDateFrom === null
            && $paymentDateTo === null
        ) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($paymentId !== null && (int) ($row['id'] ?? 0) !== $paymentId) {
                continue;
            }

            if ($invoiceId !== null && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
                continue;
            }

            if ($currency !== null && strtolower((string) ($row['currency'] ?? '')) !== $currency) {
                continue;
            }

            if ($method !== null && strtolower((string) ($row['method'] ?? '')) !== $method) {
                continue;
            }

            if ($reference !== null && stripos((string) ($row['reference'] ?? ''), $reference) === false) {
                continue;
            }

            $paymentDate = $this->normalizeDate($row['payment_date'] ?? null);
            if ($paymentDateFrom !== null && ($paymentDate === null || $paymentDate < $paymentDateFrom)) {
                continue;
            }

            if ($paymentDateTo !== null && ($paymentDate === null || $paymentDate > $paymentDateTo)) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    /** @param array<string, mixed> $rawFilters @return array<string, string> */
    public function normalizedForForm(array $rawFilters): array
    {
        return [
            'payment_id' => $this->normalizeFormText($rawFilters['payment_id'] ?? null),
            'invoice_id' => $this->normalizeFormText($rawFilters['invoice_id'] ?? null),
            'currency' => $this->normalizeFormText($rawFilters['currency'] ?? null),
            'method' => $this->normalizeFormText($rawFilters['method'] ?? null),
            'reference' => $this->normalizeFormText($rawFilters['reference'] ?? null),
            'payment_date_from' => $this->normalizeFormText($rawFilters['payment_date_from'] ?? null),
            'payment_date_to' => $this->normalizeFormText($rawFilters['payment_date_to'] ?? null),
        ];
    }

    private function normalizePositiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $parsed = (int) $value;

            return $parsed > 0 ? $parsed : null;
        }

        return null;
    }

    private function normalizeScalarText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = strtolower(trim((string) $value));

        return $text === '' ? null : $text;
    }

    private function normalizeFormText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }
}
