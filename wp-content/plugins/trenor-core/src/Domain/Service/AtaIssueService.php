<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Trenor\Core\Support\Snapshot;

final class AtaIssueService
{
    private AtaVersionProvider $versionProvider;
    private DocumentNumberGenerator $documentNumberGenerator;
    private AtaTotalsCalculator $totalsCalculator;

    public function __construct(
        AtaVersionProvider $versionProvider,
        DocumentNumberGenerator $documentNumberGenerator,
        ?AtaTotalsCalculator $totalsCalculator = null
    ) {
        $this->versionProvider = $versionProvider;
        $this->documentNumberGenerator = $documentNumberGenerator;
        $this->totalsCalculator = $totalsCalculator ?? new AtaTotalsCalculator();
    }

    /** @param array<string,mixed> $draft */
    public function buildDraftPayload(array $draft, ?DateTimeImmutable $nowUtc = null): array
    {
        $projectId = (int) ($draft['project_id'] ?? 0);
        if ($projectId <= 0) {
            throw new RuntimeException('Project is required for ÄTA draft.');
        }

        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $versionNo = $this->versionProvider->nextVersionNo($projectId);
        $documentNumber = $this->documentNumberGenerator->next('ata', $nowUtc);

        $totals = $this->totalsCalculator->calculate(
            (int) ($draft['amount_ex_vat_minor'] ?? 0),
            (float) ($draft['vat_rate_percent'] ?? 25),
            (string) ($draft['currency'] ?? 'SEK')
        );

        $snapshot = Snapshot::freeze([
            'metadata' => [
                'project_id' => $projectId,
                'estimate_id' => (int) ($draft['estimate_id'] ?? 0),
                'offert_id' => (int) ($draft['offert_id'] ?? 0),
                'invoice_id' => (int) ($draft['invoice_id'] ?? 0),
                'document_number' => $documentNumber,
                'version_no' => $versionNo,
                'title' => (string) ($draft['title'] ?? ''),
                'scope_change_text' => (string) ($draft['scope_change_text'] ?? ''),
            ],
            'totals' => $totals,
        ]);

        return [
            'project_id' => $projectId,
            'estimate_id' => $this->toNullableInt($draft['estimate_id'] ?? null),
            'offert_id' => $this->toNullableInt($draft['offert_id'] ?? null),
            'invoice_id' => $this->toNullableInt($draft['invoice_id'] ?? null),
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'draft',
            'title' => sanitize_text_field((string) ($draft['title'] ?? '')),
            'scope_change_text' => sanitize_textarea_field((string) ($draft['scope_change_text'] ?? '')),
            'amount_ex_vat_minor' => $totals['amount_ex_vat_minor'],
            'vat_rate_percent' => $totals['vat_rate_percent'],
            'vat_minor' => $totals['vat_minor'],
            'total_inc_vat_minor' => $totals['total_inc_vat_minor'],
            'currency' => $totals['currency'],
            'invoice_link_status' => 'not_invoiced',
            'snapshot_json' => (string) wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'actor_user_id' => (int) (get_current_user_id() ?: 0),
        ];
    }

    private function toNullableInt(mixed $value): ?int
    {
        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}
