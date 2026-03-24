<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class AvtalListFilter
{
    private const ALLOWED_STATUSES = ['issued', 'archived'];

    /** @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $rows, mixed $offertId, mixed $status, mixed $documentNumber): array
    {
        $offertIdValue = $this->normalizeOffertId($offertId);
        $statusValue = $this->normalizeStatus($status);
        $documentNumberValue = $this->normalizeDocumentNumber($documentNumber);

        return array_values(array_filter($rows, static function (array $row) use ($offertIdValue, $statusValue, $documentNumberValue): bool {
            if ($offertIdValue > 0 && (int) ($row['offert_id'] ?? 0) !== $offertIdValue) {
                return false;
            }

            if ($statusValue !== '' && sanitize_key((string) ($row['status'] ?? '')) !== $statusValue) {
                return false;
            }

            if ($documentNumberValue !== '' && stripos((string) ($row['document_number'] ?? ''), $documentNumberValue) === false) {
                return false;
            }

            return true;
        }));
    }

    private function normalizeOffertId(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        if (! is_scalar($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    private function normalizeStatus(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $normalized = sanitize_key((string) $value);

        return in_array($normalized, self::ALLOWED_STATUSES, true) ? $normalized : '';
    }

    private function normalizeDocumentNumber(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
