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
        $invoiceId = $this->normalizePositiveInteger($rawFilters['invoice_id'] ?? null);
        $currency = $this->normalizeCurrency($rawFilters['currency'] ?? null);
        $method = $this->normalizeMethod($rawFilters['method'] ?? null);
        $reference = $this->normalizeReference($rawFilters['reference'] ?? null);

        if ($invoiceId === null && $currency === null && $method === null && $reference === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($invoiceId !== null && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
                continue;
            }

            if ($currency !== null && strtoupper(trim((string) ($row['currency'] ?? ''))) !== $currency) {
                continue;
            }

            if ($method !== null && strtolower(trim((string) ($row['method'] ?? ''))) !== $method) {
                continue;
            }

            if ($reference !== null && stripos((string) ($row['reference'] ?? ''), $reference) === false) {
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
            'invoice_id' => $this->normalizeFormText($rawFilters['invoice_id'] ?? null),
            'currency' => $this->normalizeFormText($rawFilters['currency'] ?? null),
            'method' => $this->normalizeFormText($rawFilters['method'] ?? null),
            'reference' => $this->normalizeFormText($rawFilters['reference'] ?? null),
        ];
    }

    /** @param array<string, mixed> $rawFilters */
    public function hasActiveFilters(array $rawFilters): bool
    {
        return $this->normalizePositiveInteger($rawFilters['invoice_id'] ?? null) !== null
            || $this->normalizeCurrency($rawFilters['currency'] ?? null) !== null
            || $this->normalizeMethod($rawFilters['method'] ?? null) !== null
            || $this->normalizeReference($rawFilters['reference'] ?? null) !== null;
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

    private function normalizeCurrency(mixed $currency): ?string
    {
        if (! is_scalar($currency)) {
            return null;
        }

        $value = strtoupper(trim((string) $currency));

        return $value === '' ? null : $value;
    }

    private function normalizeMethod(mixed $method): ?string
    {
        if (! is_scalar($method)) {
            return null;
        }

        $value = strtolower(trim((string) $method));

        return $value === '' ? null : $value;
    }

    private function normalizeReference(mixed $reference): ?string
    {
        if (! is_scalar($reference)) {
            return null;
        }

        $value = trim((string) $reference);

        return $value === '' ? null : $value;
    }

    private function normalizeFormText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
