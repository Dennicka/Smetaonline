<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class EstimateTotalsCalculator
{
    /**
     * @param array<int, array<string, mixed>> $labourLines
     * @param array<int, array<string, mixed>> $materialLines
     * @param array<string, mixed> $estimatePricing
     * @return array<string, int>
     */
    public function calculate(
        array $labourLines,
        array $materialLines,
        float $vatRatePercent,
        string $taxMode = TaxMode::PRIVATE_CONSUMER,
        array $estimatePricing = []
    ): array
    {
        $labourTotalMinor = 0;
        foreach ($labourLines as $line) {
            $labourTotalMinor += (int) ($line['labour_subtotal_minor'] ?? 0);
        }

        $materialsTotalMinor = 0;
        foreach ($materialLines as $line) {
            $materialsTotalMinor += (int) ($line['subtotal_minor'] ?? 0);
        }

        $consumablesMinor = (int) ($estimatePricing['consumables_minor'] ?? 0);
        $travelMinor = (int) ($estimatePricing['travel_minor'] ?? 0);
        $wasteDisposalMinor = (int) ($estimatePricing['waste_disposal_minor'] ?? 0);
        $equipmentRentalMinor = (int) ($estimatePricing['equipment_rental_minor'] ?? 0);
        $otherCostsMinor = (int) ($estimatePricing['other_costs_minor'] ?? 0);
        $discountMinor = max(0, (int) ($estimatePricing['discount_minor'] ?? 0));
        $depositRequestedMinor = max(0, (int) ($estimatePricing['deposit_requested_minor'] ?? 0));
        $marginPercent = max(0.0, (float) ($estimatePricing['margin_percent'] ?? 0.0));

        $directCostsMinor = $consumablesMinor + $travelMinor + $wasteDisposalMinor + $equipmentRentalMinor + $otherCostsMinor;
        $costSubtotalMinor = $labourTotalMinor + $materialsTotalMinor + $directCostsMinor;
        $marginMinor = (int) round($costSubtotalMinor * ($marginPercent / 100));
        $subtotalBeforeDiscountMinor = $costSubtotalMinor + $marginMinor;
        $subtotal = max(0, $subtotalBeforeDiscountMinor - $discountMinor);
        $mode = TaxMode::normalize($taxMode);
        $vatMinor = TaxMode::isReverseCharge($mode) ? 0 : (int) round($subtotal * ($vatRatePercent / 100));
        $totalIncVatMinor = $subtotal + $vatMinor;
        $outstandingAfterDepositMinor = max(0, $totalIncVatMinor - $depositRequestedMinor);

        return [
            'labour_total_minor' => $labourTotalMinor,
            'materials_total_minor' => $materialsTotalMinor,
            'consumables_minor' => $consumablesMinor,
            'travel_minor' => $travelMinor,
            'waste_disposal_minor' => $wasteDisposalMinor,
            'equipment_rental_minor' => $equipmentRentalMinor,
            'other_costs_minor' => $otherCostsMinor,
            'direct_costs_total_minor' => $directCostsMinor,
            'cost_subtotal_minor' => $costSubtotalMinor,
            'margin_minor' => $marginMinor,
            'subtotal_before_discount_minor' => $subtotalBeforeDiscountMinor,
            'discount_minor' => $discountMinor,
            'subtotal_ex_vat_minor' => $subtotal,
            'vat_minor' => $vatMinor,
            'total_inc_vat_minor' => $totalIncVatMinor,
            'deposit_requested_minor' => $depositRequestedMinor,
            'outstanding_after_deposit_minor' => $outstandingAfterDepositMinor,
        ];
    }
}
