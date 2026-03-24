<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class MaterialCoverageCalculator
{
    public function calculateRequiredQuantity(float $workQuantity, float $coveragePerUnit): float
    {
        if ($coveragePerUnit <= 0.0) {
            return 0.0;
        }

        return round($workQuantity / $coveragePerUnit, 4);
    }
}
