<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class PaymentListSummary
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function summarize(array $rows): array
    {
        $summary = [
            'total_rows' => count($rows),
            'total_amount_minor' => 0,
            'unique_invoice_count' => 0,
            'method_counts' => [
                'manual' => 0,
                'bank' => 0,
                'swish' => 0,
                'other' => 0,
            ],
        ];

        $invoiceIds = [];
        foreach ($rows as $row) {
            $amountMinor = $row['amount_minor'] ?? 0;
            if (is_numeric($amountMinor)) {
                $summary['total_amount_minor'] += (int) $amountMinor;
            }

            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            if ($invoiceId > 0) {
                $invoiceIds[$invoiceId] = true;
            }

            $method = strtolower(trim((string) ($row['method'] ?? '')));
            if (! isset($summary['method_counts'][$method]) || $method === '') {
                $method = 'other';
            }

            $summary['method_counts'][$method]++;
        }

        $summary['unique_invoice_count'] = count($invoiceIds);

        return $summary;
    }
}
