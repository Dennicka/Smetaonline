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
        $header = is_array($snapshot['header'] ?? null) ? $snapshot['header'] : [];
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
        $snapshotPayload = Snapshot::freeze([
            'header' => $header,
            'totals' => $totals,
            'lines' => is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [],
            'material_lines' => is_array($snapshot['material_lines'] ?? null) ? $snapshot['material_lines'] : [],
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
}
