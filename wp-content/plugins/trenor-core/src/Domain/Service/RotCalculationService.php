<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use Trenor\Core\Domain\Exception\RotValidationException;

final class RotCalculationService
{
    private const ROT_RATE_PERCENT = 30;
    private const MAX_ROT_PER_PERSON_MINOR = 5000000;

    /** @param array<string,mixed> $estimate @param array<int,array<string,mixed>> $labourLines @param array<string,bool> $rotEligibilityByWorkItemId */
    public function buildSummary(array $estimate, array $labourLines, int $invoiceTotalIncVatMinor, array $rotEligibilityByWorkItemId): array
    {
        $rotRequested = (bool) ($estimate['rot_requested'] ?? false);
        $housingType = strtolower(trim((string) ($estimate['housing_type'] ?? '')));
        $isNewBuild = (bool) ($estimate['rot_is_new_build'] ?? false);
        $buyers = $this->normalizeBuyers((string) ($estimate['rot_buyers_json'] ?? ''));
        $eligibleLabourMinor = $this->eligibleLabourMinor($labourLines, $rotEligibilityByWorkItemId);

        if (! $rotRequested) {
            return $this->zeroSummary(false, $housingType, $buyers, $eligibleLabourMinor, 'not_requested', '', $invoiceTotalIncVatMinor);
        }

        $ineligibilityReason = '';
        $eligibilityStatus = 'preliminary_approved';
        if (! in_array($housingType, ['smahus', 'bostadsratt', 'agarlagenhet'], true)) {
            $eligibilityStatus = 'denied';
            $ineligibilityReason = 'unsupported_housing_type';
        } elseif ($isNewBuild) {
            $eligibilityStatus = 'denied';
            $ineligibilityReason = 'new_build_not_eligible';
        } elseif ($eligibleLabourMinor <= 0) {
            $eligibilityStatus = 'denied';
            $ineligibilityReason = 'no_eligible_labour';
        } elseif ($buyers === []) {
            $eligibilityStatus = 'needs_confirmation';
            $ineligibilityReason = 'missing_buyers';
        }

        if ($eligibilityStatus !== 'preliminary_approved') {
            return $this->zeroSummary(true, $housingType, $buyers, $eligibleLabourMinor, $eligibilityStatus, $ineligibilityReason, $invoiceTotalIncVatMinor);
        }

        $preliminaryRotMinor = (int) round($eligibleLabourMinor * (self::ROT_RATE_PERCENT / 100));
        $allocation = $this->allocateRot($buyers, $preliminaryRotMinor);
        $allocatedTotalMinor = 0;
        foreach ($allocation as $row) {
            $allocatedTotalMinor += (int) ($row['allocated_rot_minor'] ?? 0);
        }

        if ($allocatedTotalMinor > $preliminaryRotMinor) {
            throw new RotValidationException('ROT allocation exceeds preliminary ROT amount.');
        }

        return [
            'rot_requested' => true,
            'housing_type' => $housingType,
            'rot_eligibility_status' => 'preliminary_approved',
            'rot_ineligibility_reason' => '',
            'rot_eligible_labour_minor' => $eligibleLabourMinor,
            'preliminary_rot_minor' => $allocatedTotalMinor,
            'rot_buyer_count' => count($buyers),
            'rot_buyers' => $buyers,
            'rot_allocation' => $allocation,
            'rot_property_reference' => trim((string) ($estimate['rot_property_reference'] ?? '')),
            'amount_before_rot_minor' => max(0, $invoiceTotalIncVatMinor),
            'amount_after_preliminary_rot_minor' => max(0, $invoiceTotalIncVatMinor - $allocatedTotalMinor),
        ];
    }

    /** @param array<int,array<string,mixed>> $buyers */
    private function allocateRot(array $buyers, int $preliminaryRotMinor): array
    {
        $weights = [];
        $weightTotal = 0.0;
        foreach ($buyers as $buyer) {
            $sharePercent = (float) ($buyer['share_percent'] ?? 0);
            if ($sharePercent <= 0) {
                $sharePercent = 100 / max(1, count($buyers));
            }
            $weights[] = $sharePercent;
            $weightTotal += $sharePercent;
        }

        if ($weightTotal <= 0.0) {
            throw new RotValidationException('Invalid ROT buyer allocation weights.');
        }

        $rows = [];
        $remaining = $preliminaryRotMinor;
        foreach ($buyers as $index => $buyer) {
            $weight = $weights[$index] / $weightTotal;
            $rawAllocation = (int) floor($preliminaryRotMinor * $weight);
            $capped = min($rawAllocation, self::MAX_ROT_PER_PERSON_MINOR);
            $rows[] = [
                'buyer_index' => $index,
                'name' => (string) ($buyer['name'] ?? ''),
                'personal_identity' => (string) ($buyer['personal_identity'] ?? ''),
                'share_percent' => round($weights[$index], 4),
                'allocated_rot_minor' => $capped,
                'cap_minor' => self::MAX_ROT_PER_PERSON_MINOR,
            ];
            $remaining -= $capped;
        }

        if ($remaining > 0) {
            foreach ($rows as $idx => $row) {
                $current = (int) $row['allocated_rot_minor'];
                $headroom = self::MAX_ROT_PER_PERSON_MINOR - $current;
                if ($headroom <= 0) {
                    continue;
                }

                $delta = min($headroom, $remaining);
                $rows[$idx]['allocated_rot_minor'] = $current + $delta;
                $remaining -= $delta;
                if ($remaining <= 0) {
                    break;
                }
            }
        }

        return $rows;
    }

    /** @param array<int,array<string,mixed>> $labourLines @param array<string,bool> $rotEligibilityByWorkItemId */
    private function eligibleLabourMinor(array $labourLines, array $rotEligibilityByWorkItemId): int
    {
        $total = 0;
        foreach ($labourLines as $line) {
            $workItemId = (int) ($line['work_item_id'] ?? 0);
            if ($workItemId <= 0) {
                continue;
            }

            if (! ($rotEligibilityByWorkItemId[(string) $workItemId] ?? false)) {
                continue;
            }

            $total += (int) ($line['labour_subtotal_minor'] ?? 0);
        }

        return max(0, $total);
    }

    /** @return array<int,array{name:string,personal_identity:string,share_percent:float}> */
    private function normalizeBuyers(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $buyers = [];
        foreach ($decoded as $buyer) {
            if (! is_array($buyer)) {
                continue;
            }

            $personalIdentity = trim((string) ($buyer['personal_identity'] ?? ''));
            if ($personalIdentity === '') {
                continue;
            }

            $buyers[] = [
                'name' => trim((string) ($buyer['name'] ?? '')),
                'personal_identity' => $personalIdentity,
                'share_percent' => max(0.0, (float) ($buyer['share_percent'] ?? 0)),
            ];
        }

        return $buyers;
    }

    /** @param array<int,array{name:string,personal_identity:string,share_percent:float}> $buyers */
    private function zeroSummary(bool $rotRequested, string $housingType, array $buyers, int $eligibleLabourMinor, string $status, string $reason, int $invoiceTotalIncVatMinor): array
    {
        return [
            'rot_requested' => $rotRequested,
            'housing_type' => $housingType,
            'rot_eligibility_status' => $status,
            'rot_ineligibility_reason' => $reason,
            'rot_eligible_labour_minor' => max(0, $eligibleLabourMinor),
            'preliminary_rot_minor' => 0,
            'rot_buyer_count' => count($buyers),
            'rot_buyers' => $buyers,
            'rot_allocation' => [],
            'rot_property_reference' => '',
            'amount_before_rot_minor' => max(0, $invoiceTotalIncVatMinor),
            'amount_after_preliminary_rot_minor' => max(0, $invoiceTotalIncVatMinor),
        ];
    }
}
