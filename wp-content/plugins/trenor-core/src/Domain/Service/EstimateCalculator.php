<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use Trenor\Core\Domain\Exception\EstimateCalculationException;

final class EstimateCalculator
{
    /** @param array<string, mixed> $line */
    public function calculateLabourLine(array $line): array
    {
        $norm = (float) ($line['norm_per_hour_snapshot'] ?? 0);
        if ($norm <= 0.0) {
            throw new EstimateCalculationException('Norm per hour must be greater than zero.');
        }

        $quantity = (float) ($line['quantity'] ?? 0);
        $complexity = (float) ($line['complexity_coeff'] ?? 1);
        $surface = (float) ($line['surface_coeff'] ?? 1);
        $access = (float) ($line['access_coeff'] ?? 1);
        $urgency = (float) ($line['urgency_coeff'] ?? 1);
        $manualHoursDelta = (float) ($line['manual_hours_delta'] ?? 0);
        $labourRateMinor = (int) ($line['labour_rate_minor_snapshot'] ?? 0);

        $hours = (($quantity / $norm) * $complexity * $surface * $access * $urgency) + $manualHoursDelta;
        $labourSubtotalMinor = (int) round($hours * $labourRateMinor);

        return [
            'hours' => round($hours, 4),
            'labour_subtotal_minor' => $labourSubtotalMinor,
        ];
    }
}
