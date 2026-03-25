<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class EstimateTotalsCalculator
{
    /** @param array<int, array<string, mixed>> $labourLines @param array<int, array<string, mixed>> $materialLines */
    public function calculate(array $labourLines, array $materialLines, float $vatRatePercent, string $taxMode = TaxMode::PRIVATE_CONSUMER): array
    {
        $labourTotalMinor = 0;
        foreach ($labourLines as $line) {
            $labourTotalMinor += (int) ($line['labour_subtotal_minor'] ?? 0);
        }

        $materialsTotalMinor = 0;
        foreach ($materialLines as $line) {
            $materialsTotalMinor += (int) ($line['subtotal_minor'] ?? 0);
        }

        $subtotal = $labourTotalMinor + $materialsTotalMinor;
        $mode = TaxMode::normalize($taxMode);
        $vatMinor = TaxMode::isReverseCharge($mode) ? 0 : (int) round($subtotal * ($vatRatePercent / 100));

        return [
            'labour_total_minor' => $labourTotalMinor,
            'materials_total_minor' => $materialsTotalMinor,
            'subtotal_ex_vat_minor' => $subtotal,
            'vat_minor' => $vatMinor,
            'total_inc_vat_minor' => $subtotal + $vatMinor,
        ];
    }
}
