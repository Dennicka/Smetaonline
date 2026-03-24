<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Trenor\Core\Support\Snapshot;

final class AvtalFromOffertService
{
    private AvtalVersionProvider $repository;
    private DocumentNumberGenerator $documentSequenceGenerator;

    public function __construct(AvtalVersionProvider $repository, DocumentNumberGenerator $documentSequenceGenerator)
    {
        $this->repository = $repository;
        $this->documentSequenceGenerator = $documentSequenceGenerator;
    }

    /**
     * @param array<string, mixed> $offertRow
     * @param array{header?: array<string, mixed>, totals?: array<string, mixed>, lines?: array<int, mixed>, material_lines?: array<int, mixed>, metadata?: array<string, mixed>} $offertSnapshot
     * @return array<string, mixed>
     */
    public function buildPayload(array $offertRow, array $offertSnapshot, ?DateTimeImmutable $issuedAtUtc = null): array
    {
        $status = sanitize_key((string) ($offertRow['status'] ?? ''));
        if (! (new AvtalIssuePolicy())->canIssueFromOffertStatus($status)) {
            throw new RuntimeException('Avtal can be issued only from accepted offert.');
        }

        $issuedAtUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $offertId = (int) ($offertRow['id'] ?? 0);
        $estimateId = (int) ($offertRow['estimate_id'] ?? 0);
        if ($offertId <= 0 || $estimateId <= 0) {
            throw new RuntimeException('Avtal source is invalid: missing offert/estimate linkage.');
        }

        $versionNo = $this->repository->nextVersionNo($offertId);
        $documentNumber = $this->documentSequenceGenerator->next('avt', $issuedAtUtc);

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
                'avtal_version_no' => $versionNo,
                'document_number' => $documentNumber,
                'issued_at_utc' => $issuedAtUtc->format('Y-m-d H:i:s'),
            ],
        ]);

        return [
            'offert_id' => $offertId,
            'estimate_id' => $estimateId,
            'project_id' => (int) ($header['project_id'] ?? 0),
            'client_id' => (int) ($header['client_id'] ?? 0),
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'title' => (string) ($header['title'] ?? ''),
            'currency' => strtoupper((string) ($offertRow['currency'] ?? 'SEK')),
            'vat_rate_percent' => (float) ($offertRow['vat_rate_percent'] ?? 25),
            'total_inc_vat_minor' => (int) ($totals['total_inc_vat_minor'] ?? 0),
            'snapshot_json' => (string) wp_json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'issued_at' => $issuedAtUtc->format('Y-m-d H:i:s'),
            'actor_user_id' => (int) (get_current_user_id() ?: 0),
        ];
    }
}
