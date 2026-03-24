<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OffertListFilter
{
    private const ALLOWED_STATUSES = ['issued', 'accepted', 'rejected', 'archived'];

    /** @param array<int, array<string, mixed>> $rows */
    public function apply(array $rows, mixed $estimateId, mixed $status, mixed $documentNumber): array
    {
        $normalizedEstimateId = $this->normalizeEstimateId($estimateId);
        $normalizedStatus = $this->normalizeStatus($status);
        $normalizedDocumentNumber = $this->normalizeDocumentNumber($documentNumber);

        if ($normalizedEstimateId === null && $normalizedStatus === null && $normalizedDocumentNumber === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($normalizedEstimateId !== null && (int) ($row['estimate_id'] ?? 0) !== $normalizedEstimateId) {
                continue;
            }

            if ($normalizedStatus !== null && strtolower((string) ($row['status'] ?? '')) !== $normalizedStatus) {
                continue;
            }

            if ($normalizedDocumentNumber !== null && stripos((string) ($row['document_number'] ?? ''), $normalizedDocumentNumber) === false) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    public function isEstimateIdFilterActive(mixed $estimateId): bool
    {
        return $this->normalizeEstimateId($estimateId) !== null;
    }

    public function isStatusFilterActive(mixed $status): bool
    {
        return $this->normalizeStatus($status) !== null;
    }

    public function isDocumentNumberFilterActive(mixed $documentNumber): bool
    {
        return $this->normalizeDocumentNumber($documentNumber) !== null;
    }

    private function normalizeEstimateId(mixed $estimateId): ?int
    {
        if (is_int($estimateId)) {
            return $estimateId > 0 ? $estimateId : null;
        }

        if (is_string($estimateId) && preg_match('/^\d+$/', $estimateId) === 1) {
            $parsed = (int) $estimateId;

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
}
