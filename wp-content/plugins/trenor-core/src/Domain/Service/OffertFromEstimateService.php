<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use Trenor\Core\Support\Snapshot;

final class OffertFromEstimateService
{
    private OffertVersionProvider $offertRepository;
    private DocumentNumberGenerator $documentSequenceGenerator;

    public function __construct(OffertVersionProvider $offertRepository, DocumentNumberGenerator $documentSequenceGenerator)
    {
        $this->offertRepository = $offertRepository;
        $this->documentSequenceGenerator = $documentSequenceGenerator;
    }

    /** @param array<string,mixed> $estimateHeader @param array<int,array<string,mixed>> $estimateLines @param array<int,array<string,mixed>> $estimateMaterialLines @param array<string,mixed> $totals @param array<string,mixed> $rotSummary @return array<string,mixed> */
    public function buildPayload(array $estimateHeader, array $estimateLines, array $estimateMaterialLines, array $totals, ?DateTimeImmutable $issuedAtUtc = null, array $rotSummary = []): array
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
            'rot' => $rotSummary,
        ]);

        return [
            'estimate_id' => $estimateId,
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'currency' => strtoupper((string) ($estimateHeader['currency'] ?? 'SEK')),
            'tax_mode' => TaxMode::normalize($estimateHeader['tax_mode'] ?? null),
            'reverse_charge_note' => (string) ($estimateHeader['reverse_charge_note'] ?? ''),
            'client_company_name' => (string) ($estimateHeader['client_company_name'] ?? ''),
            'client_org_number' => (string) ($estimateHeader['client_org_number'] ?? ''),
            'client_vat_number' => (string) ($estimateHeader['client_vat_number'] ?? ''),
            'vat_rate_percent' => (float) ($estimateHeader['vat_rate_percent'] ?? 25),
            'labour_total_minor' => (int) ($totals['labour_total_minor'] ?? 0),
            'materials_total_minor' => (int) ($totals['materials_total_minor'] ?? 0),
            'subtotal_ex_vat_minor' => (int) ($totals['subtotal_ex_vat_minor'] ?? 0),
            'vat_minor' => (int) ($totals['vat_minor'] ?? 0),
            'total_inc_vat_minor' => (int) ($totals['total_inc_vat_minor'] ?? 0),
            'rot_requested' => ! empty($rotSummary['rot_requested']) ? 1 : 0,
            'housing_type' => (string) ($rotSummary['housing_type'] ?? ''),
            'rot_eligibility_status' => (string) ($rotSummary['rot_eligibility_status'] ?? 'not_requested'),
            'rot_ineligibility_reason' => (string) ($rotSummary['rot_ineligibility_reason'] ?? ''),
            'rot_eligible_labour_minor' => (int) ($rotSummary['rot_eligible_labour_minor'] ?? 0),
            'preliminary_rot_minor' => (int) ($rotSummary['preliminary_rot_minor'] ?? 0),
            'total_after_preliminary_rot_minor' => (int) ($rotSummary['amount_after_preliminary_rot_minor'] ?? ($totals['total_inc_vat_minor'] ?? 0)),
            'rot_buyer_count' => (int) ($rotSummary['rot_buyer_count'] ?? 0),
            'rot_buyers_json' => (string) wp_json_encode($rotSummary['rot_buyers'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'rot_allocation_json' => (string) wp_json_encode($rotSummary['rot_allocation'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'rot_property_reference' => (string) ($rotSummary['rot_property_reference'] ?? ''),
            'snapshot_json' => (string) wp_json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'issued_at' => $issuedAtUtc->format('Y-m-d H:i:s'),
        ];
    }
}
