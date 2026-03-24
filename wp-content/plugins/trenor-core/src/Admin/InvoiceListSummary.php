<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class InvoiceListSummary
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    public function summarize(array $rows): array
    {
        $summary = [
            'total_rows' => count($rows),
            'issued' => 0,
            'partially_paid' => 0,
            'paid' => 0,
            'archived' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        return $summary;
    }
}
