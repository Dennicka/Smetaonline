<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class InvoiceListFilter
{
    private const ALLOWED_STATUSES = ['issued', 'partially_paid', 'paid', 'archived'];

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $rawFilters
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $rows, array $rawFilters): array
    {
        $offertId = $this->normalizePositiveInteger($rawFilters['offert_id'] ?? null);
        $estimateId = $this->normalizePositiveInteger($rawFilters['estimate_id'] ?? null);
        $status = $this->normalizeStatus($rawFilters['status'] ?? null);
        $documentNumber = $this->normalizeDocumentNumber($rawFilters['document_number'] ?? null);

        if ($offertId === null && $estimateId === null && $status === null && $documentNumber === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($offertId !== null && (int) ($row['offert_id'] ?? 0) !== $offertId) {
                continue;
            }

            if ($estimateId !== null && (int) ($row['estimate_id'] ?? 0) !== $estimateId) {
                continue;
            }

            if ($status !== null && strtolower((string) ($row['status'] ?? '')) !== $status) {
                continue;
            }

            if ($documentNumber !== null && stripos((string) ($row['document_number'] ?? ''), $documentNumber) === false) {
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
            'offert_id' => $this->normalizeFormText($rawFilters['offert_id'] ?? null),
            'estimate_id' => $this->normalizeFormText($rawFilters['estimate_id'] ?? null),
            'status' => $this->normalizeFormText($rawFilters['status'] ?? null),
            'document_number' => $this->normalizeFormText($rawFilters['document_number'] ?? null),
        ];
    }

    /** @param array<string, mixed> $rawFilters */
    public function hasActiveFilters(array $rawFilters): bool
    {
        return $this->normalizePositiveInteger($rawFilters['offert_id'] ?? null) !== null
            || $this->normalizePositiveInteger($rawFilters['estimate_id'] ?? null) !== null
            || $this->normalizeStatus($rawFilters['status'] ?? null) !== null
            || $this->normalizeDocumentNumber($rawFilters['document_number'] ?? null) !== null;
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

    private function normalizeStatus(mixed $status): ?string
    {
        if (! is_scalar($status)) {
            return null;
        }

        $value = strtolower(trim((string) $status));
        if ($value === '' || ! in_array($value, self::ALLOWED_STATUSES, true)) {
            return null;
        }

        return $value;
    }

    private function normalizeDocumentNumber(mixed $documentNumber): ?string
    {
        if (! is_scalar($documentNumber)) {
            return null;
        }

        $value = trim((string) $documentNumber);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function normalizeFormText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
