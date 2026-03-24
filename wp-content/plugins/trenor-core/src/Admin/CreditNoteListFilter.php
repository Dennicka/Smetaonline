<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class CreditNoteListFilter
{
    private const ALLOWED_STATUSES = ['issued', 'archived'];

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $rawFilters
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $rows, array $rawFilters): array
    {
        $invoiceId = $this->normalizePositiveInteger($rawFilters['invoice_id'] ?? null);
        $status = $this->normalizeStatus($rawFilters['status'] ?? null);
        $documentNumber = $this->normalizeDocumentNumber($rawFilters['document_number'] ?? null);

        if ($invoiceId === null && $status === null && $documentNumber === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($invoiceId !== null && (int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
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
            'invoice_id' => $this->normalizeFormText($rawFilters['invoice_id'] ?? null),
            'status' => $this->normalizeFormText($rawFilters['status'] ?? null),
            'document_number' => $this->normalizeFormText($rawFilters['document_number'] ?? null),
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
