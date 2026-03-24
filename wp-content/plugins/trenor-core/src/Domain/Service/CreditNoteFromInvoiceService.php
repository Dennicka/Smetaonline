<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Trenor\Core\Admin\CreditNoteSnapshotReader;
use Trenor\Core\Support\Snapshot;

final class CreditNoteFromInvoiceService
{
    private CreditNoteVersionProvider $versionProvider;
    private DocumentNumberGenerator $documentNumberGenerator;
    private CreditNoteSnapshotReader $snapshotReader;

    public function __construct(
        CreditNoteVersionProvider $versionProvider,
        DocumentNumberGenerator $documentNumberGenerator,
        ?CreditNoteSnapshotReader $snapshotReader = null
    ) {
        $this->versionProvider = $versionProvider;
        $this->documentNumberGenerator = $documentNumberGenerator;
        $this->snapshotReader = $snapshotReader ?? new CreditNoteSnapshotReader();
    }

    /**
     * @param array<string,mixed> $invoiceRow
     * @return array<string,mixed>
     */
    public function buildPayload(array $invoiceRow, ?DateTimeImmutable $issuedAtUtc = null): array
    {
        $invoiceId = (int) ($invoiceRow['id'] ?? 0);
        if ($invoiceId <= 0) {
            throw new RuntimeException('Source invoice not found.');
        }

        $status = sanitize_key((string) ($invoiceRow['status'] ?? ''));
        if ($status === 'archived') {
            throw new RuntimeException('Cannot issue credit note from archived invoice.');
        }

        $snapshot = $this->snapshotReader->read($invoiceRow);
        if (
            $snapshot['header'] === []
            || $snapshot['totals'] === []
            || (is_array($snapshot['lines']) && is_array($snapshot['material_lines']) && $snapshot['lines'] === [] && $snapshot['material_lines'] === [])
        ) {
            throw new RuntimeException('Cannot issue credit note: source invoice snapshot is missing or invalid.');
        }

        $issuedAtUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $versionNo = $this->versionProvider->nextVersionNo($invoiceId);
        $documentNumber = $this->documentNumberGenerator->next('crn', $issuedAtUtc);

        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $header = $this->normalizeHeader(is_array($snapshot['header'] ?? null) ? $snapshot['header'] : []);
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
        $lines = $this->normalizeLineList(is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : []);
        $materialLines = $this->normalizeLineList(is_array($snapshot['material_lines'] ?? null) ? $snapshot['material_lines'] : []);
        $snapshotPayload = Snapshot::freeze([
            'header' => $header,
            'totals' => $totals,
            'lines' => $lines,
            'material_lines' => $materialLines,
            'metadata' => [
                'source_invoice_id' => $invoiceId,
                'source_invoice_document_number' => (string) ($invoiceRow['document_number'] ?? ''),
                'source_offert_id' => (int) ($invoiceRow['offert_id'] ?? 0),
                'source_estimate_id' => (int) ($invoiceRow['estimate_id'] ?? 0),
                'source_estimate_title' => (string) ($metadata['source_estimate_title'] ?? $header['title'] ?? ''),
                'credit_note_version_no' => $versionNo,
                'document_number' => $documentNumber,
                'issued_at_utc' => $issuedAtUtc->format('Y-m-d H:i:s'),
            ],
        ]);

        return [
            'invoice_id' => $invoiceId,
            'offert_id' => (int) ($invoiceRow['offert_id'] ?? 0),
            'estimate_id' => (int) ($invoiceRow['estimate_id'] ?? 0),
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'currency' => strtoupper((string) ($invoiceRow['currency'] ?? 'SEK')),
            'vat_rate_percent' => (float) ($invoiceRow['vat_rate_percent'] ?? 25),
            'labour_total_minor' => (int) ($totals['labour_total_minor'] ?? 0),
            'materials_total_minor' => (int) ($totals['materials_total_minor'] ?? 0),
            'subtotal_ex_vat_minor' => (int) ($totals['subtotal_ex_vat_minor'] ?? 0),
            'vat_minor' => (int) ($totals['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($totals['total_inc_vat_minor'] ?? 0),
            'snapshot_json' => (string) wp_json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'issued_at' => $issuedAtUtc->format('Y-m-d H:i:s'),
        ];
    }

    /** @param array<string, mixed> $header @return array<string, mixed> */
    private function normalizeHeader(array $header): array
    {
        if (isset($header['vat_rate_percent']) && is_numeric($header['vat_rate_percent'])) {
            $header['vat_rate_percent'] = (float) $header['vat_rate_percent'];
        }

        return $header;
    }

    /** @param array<int, mixed> $lines @return array<int, mixed> */
    private function normalizeLineList(array $lines): array
    {
        $decimalKeys = [
            'quantity',
            'hours',
            'calculated_hours',
            'coverage_snapshot',
            'norm_per_hour_snapshot',
            'manual_hours_delta',
            'complexity_coeff',
            'surface_coeff',
            'access_coeff',
            'urgency_coeff',
            'vat_rate_percent',
        ];

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            foreach ($decimalKeys as $key) {
                if (isset($line[$key]) && is_numeric($line[$key])) {
                    $line[$key] = (float) $line[$key];
                }
            }

            $lines[$index] = $line;
        }

        return $lines;
    }
}
