<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class BusinessEffectFingerprint
{
    /** @param array<string, mixed> $estimate @param array<int, array<string, mixed>> $lines @param array<int, array<string, mixed>> $materialLines @param array<string, mixed> $totals */
    public function offertForEstimate(array $estimate, array $lines, array $materialLines, array $totals): string
    {
        $normalizedLines = array_map([$this, 'normalizeEstimateLine'], $lines);
        $normalizedMaterialLines = array_map([$this, 'normalizeEstimateMaterialLine'], $materialLines);

        usort($normalizedLines, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);
        usort($normalizedMaterialLines, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);

        return $this->hash([
            'estimate' => [
                'id' => (int) ($estimate['id'] ?? 0),
                'status' => (string) ($estimate['status'] ?? ''),
                'currency' => strtoupper((string) ($estimate['currency'] ?? 'SEK')),
                'vat_rate_percent' => (string) ($estimate['vat_rate_percent'] ?? ''),
                'labour_rate_minor' => (int) ($estimate['labour_rate_minor'] ?? 0),
                'title' => (string) ($estimate['title'] ?? ''),
                'notes' => (string) ($estimate['notes'] ?? ''),
                'calculated_at' => (string) ($estimate['calculated_at'] ?? ''),
            ],
            'lines' => $normalizedLines,
            'material_lines' => $normalizedMaterialLines,
            'totals' => [
                'labour_total_minor' => (int) ($totals['labour_total_minor'] ?? 0),
                'materials_total_minor' => (int) ($totals['materials_total_minor'] ?? 0),
                'subtotal_ex_vat_minor' => (int) ($totals['subtotal_ex_vat_minor'] ?? 0),
                'vat_minor' => (int) ($totals['vat_minor'] ?? 0),
                'total_inc_vat_minor' => (int) ($totals['total_inc_vat_minor'] ?? 0),
            ],
        ]);
    }

    /** @param array<string, mixed> $offert @param array<string, mixed> $snapshot */
    public function invoiceForOffert(array $offert, array $snapshot): string
    {
        return $this->hash([
            'offert' => [
                'id' => (int) ($offert['id'] ?? 0),
                'status' => (string) ($offert['status'] ?? ''),
                'currency' => strtoupper((string) ($offert['currency'] ?? 'SEK')),
                'vat_rate_percent' => (string) ($offert['vat_rate_percent'] ?? ''),
                'total_inc_vat_minor' => (int) ($offert['total_inc_vat_minor'] ?? 0),
                'snapshot_json' => (string) ($offert['snapshot_json'] ?? ''),
            ],
            'snapshot' => $snapshot,
        ]);
    }

    /** @param array<string, mixed> $invoice */
    public function creditNoteForInvoice(array $invoice): string
    {
        return $this->hash([
            'invoice' => [
                'id' => (int) ($invoice['id'] ?? 0),
                'status' => (string) ($invoice['status'] ?? ''),
                'currency' => strtoupper((string) ($invoice['currency'] ?? 'SEK')),
                'vat_rate_percent' => (string) ($invoice['vat_rate_percent'] ?? ''),
                'total_inc_vat_minor' => (int) ($invoice['total_inc_vat_minor'] ?? 0),
                'snapshot_json' => (string) ($invoice['snapshot_json'] ?? ''),
            ],
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function paymentPayload(array $payload): string
    {
        return $this->hash([
            'invoice_id' => (int) ($payload['invoice_id'] ?? 0),
            'payment_date' => trim((string) ($payload['payment_date'] ?? '')),
            'amount_minor' => (int) ($payload['amount_minor'] ?? 0),
            'currency' => strtoupper(trim((string) ($payload['currency'] ?? 'SEK'))),
            'method' => strtolower(trim((string) ($payload['method'] ?? 'manual'))),
            'reference' => trim((string) ($payload['reference'] ?? '')),
            'note' => trim((string) ($payload['note'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $line @return array<string, mixed> */
    private function normalizeEstimateLine(array $line): array
    {
        return [
            'id' => (int) ($line['id'] ?? 0),
            'work_item_id' => (int) ($line['work_item_id'] ?? 0),
            'room_id' => (int) ($line['room_id'] ?? 0),
            'quantity' => (string) ($line['quantity'] ?? ''),
            'unit_code' => (string) ($line['unit_code'] ?? ''),
            'complexity' => (string) ($line['complexity'] ?? ''),
            'price_minor' => (int) ($line['price_minor'] ?? 0),
            'line_total_minor' => (int) ($line['line_total_minor'] ?? 0),
            'status' => (string) ($line['status'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $line @return array<string, mixed> */
    private function normalizeEstimateMaterialLine(array $line): array
    {
        return [
            'id' => (int) ($line['id'] ?? 0),
            'material_id' => (int) ($line['material_id'] ?? 0),
            'room_id' => (int) ($line['room_id'] ?? 0),
            'quantity' => (string) ($line['quantity'] ?? ''),
            'unit_code' => (string) ($line['unit_code'] ?? ''),
            'sell_price_minor_snapshot' => (int) ($line['sell_price_minor_snapshot'] ?? 0),
            'subtotal_minor' => (int) ($line['subtotal_minor'] ?? 0),
            'status' => (string) ($line['status'] ?? ''),
            'consumption_note' => (string) ($line['consumption_note'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) wp_json_encode($payload));
    }
}
