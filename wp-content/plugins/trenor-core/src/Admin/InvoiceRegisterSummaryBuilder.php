<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class InvoiceRegisterSummaryBuilder
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    public function build(array $rows): array
    {
        $summary = [
            'invoices_count' => 0,
            'issued_total_minor' => 0,
            'paid_total_minor' => 0,
            'outstanding_total_minor' => 0,
            'fully_paid_count' => 0,
            'partially_paid_count' => 0,
            'archived_count' => 0,
        ];

        foreach ($rows as $row) {
            $summary['invoices_count']++;
            $summary['issued_total_minor'] += max($this->toInt($row['total_inc_vat_minor'] ?? null), 0);
            $summary['paid_total_minor'] += max($this->toInt($row['paid_total_minor'] ?? null), 0);
            $summary['outstanding_total_minor'] += max($this->toInt($row['outstanding_minor'] ?? null), 0);

            if (strtolower((string) ($row['computed_status'] ?? '')) === 'paid') {
                $summary['fully_paid_count']++;
            }

            if (strtolower((string) ($row['computed_status'] ?? '')) === 'partially_paid') {
                $summary['partially_paid_count']++;
            }

            if (strtolower((string) ($row['stored_status'] ?? '')) === 'archived') {
                $summary['archived_count']++;
            }
        }

        return $summary;
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
