<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Trenor\Core\Support\Snapshot;

final class InvoiceFromOffertService
{
    private InvoiceVersionProvider $invoiceRepository;
    private DocumentNumberGenerator $documentSequenceGenerator;

    public function __construct(InvoiceVersionProvider $invoiceRepository, DocumentNumberGenerator $documentSequenceGenerator)
    {
        $this->invoiceRepository = $invoiceRepository;
        $this->documentSequenceGenerator = $documentSequenceGenerator;
    }

    /**
     * @param array<string,mixed> $offertRow
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $offertSnapshot
     * @return array<string,mixed>
     */
    public function buildPayload(array $offertRow, array $offertSnapshot, ?DateTimeImmutable $issuedAtUtc = null): array
    {
        $status = sanitize_key((string) ($offertRow['status'] ?? ''));
        if ($status !== 'accepted') {
            throw new RuntimeException('Invoice can be issued only from accepted offert.');
        }

        $issuedAtUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $offertId = (int) ($offertRow['id'] ?? 0);
        $estimateId = (int) ($offertRow['estimate_id'] ?? 0);

        $versionNo = $this->invoiceRepository->nextVersionNo($offertId);
        $documentNumber = $this->documentSequenceGenerator->next('inv', $issuedAtUtc);

        $header = is_array($offertSnapshot['header'] ?? null) ? $offertSnapshot['header'] : [];
        $totals = is_array($offertSnapshot['totals'] ?? null) ? $offertSnapshot['totals'] : [];
        $lines = is_array($offertSnapshot['lines'] ?? null) ? $offertSnapshot['lines'] : [];
        $materialLines = is_array($offertSnapshot['material_lines'] ?? null) ? $offertSnapshot['material_lines'] : [];

        $snapshotPayload = Snapshot::freeze([
            'header' => $header,
            'totals' => $totals,
            'lines' => $lines,
            'material_lines' => $materialLines,
            'metadata' => [
                'source_offert_id' => $offertId,
                'source_offert_document_number' => (string) ($offertRow['document_number'] ?? ''),
                'source_estimate_id' => $estimateId,
                'source_estimate_title' => (string) ($header['title'] ?? ''),
                'invoice_version_no' => $versionNo,
                'document_number' => $documentNumber,
                'issued_at_utc' => $issuedAtUtc->format('Y-m-d H:i:s'),
            ],
        ]);

        return [
            'offert_id' => $offertId,
            'estimate_id' => $estimateId,
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'currency' => strtoupper((string) ($offertRow['currency'] ?? 'SEK')),
            'vat_rate_percent' => (float) ($offertRow['vat_rate_percent'] ?? 25),
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
