<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use Trenor\Core\Database\OffertRepository;
use Trenor\Core\Support\Snapshot;

final class OffertFromEstimateService
{
    private OffertRepository $offertRepository;
    private DocumentSequenceGenerator $documentSequenceGenerator;

    public function __construct(OffertRepository $offertRepository, DocumentSequenceGenerator $documentSequenceGenerator)
    {
        $this->offertRepository = $offertRepository;
        $this->documentSequenceGenerator = $documentSequenceGenerator;
    }

    /** @param array<string,mixed> $estimateHeader @param array<int,array<string,mixed>> $estimateLines @param array<int,array<string,mixed>> $estimateMaterialLines @param array<string,mixed> $totals @return array<string,mixed> */
    public function buildPayload(array $estimateHeader, array $estimateLines, array $estimateMaterialLines, array $totals, ?DateTimeImmutable $issuedAtUtc = null): array
    {
        $issuedAtUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $estimateId = (int) ($estimateHeader['id'] ?? 0);

        $versionNo = $this->offertRepository->nextVersionNo($estimateId);
        $documentNumber = $this->documentSequenceGenerator->next('off', $issuedAtUtc);

        $snapshotPayload = Snapshot::freeze([
            'header' => $estimateHeader,
            'lines' => $estimateLines,
            'material_lines' => $estimateMaterialLines,
            'totals' => $totals,
            'metadata' => [
                'source_estimate_id' => $estimateId,
                'source_estimate_title' => (string) ($estimateHeader['title'] ?? ''),
                'offert_version_no' => $versionNo,
                'document_number' => $documentNumber,
                'issued_at_utc' => $issuedAtUtc->format('Y-m-d H:i:s'),
            ],
        ]);

        return [
            'estimate_id' => $estimateId,
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'currency' => strtoupper((string) ($estimateHeader['currency'] ?? 'SEK')),
            'vat_rate_percent' => (float) ($estimateHeader['vat_rate_percent'] ?? 25),
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
