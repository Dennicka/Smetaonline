<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Exception\EstimateCalculationException;
use Trenor\Core\Domain\Service\EstimateCalculator;

final class EstimateCalculatorTest extends TestCase
{
    public function testCalculatesHoursAndSubtotal(): void
    {
        $result = (new EstimateCalculator())->calculateLabourLine([
            'quantity' => 100,
            'norm_per_hour_snapshot' => 20,
            'complexity_coeff' => 1.2,
            'surface_coeff' => 1.1,
            'access_coeff' => 1.05,
            'urgency_coeff' => 1.0,
            'manual_hours_delta' => 0.5,
            'labour_rate_minor_snapshot' => 6500,
        ]);

        self::assertSame(7.43, round((float) $result['hours'], 2));
        self::assertSame(48295, $result['labour_subtotal_minor']);
    }

    public function testRoundingAppliedToSubtotal(): void
    {
        $result = (new EstimateCalculator())->calculateLabourLine([
            'quantity' => 10,
            'norm_per_hour_snapshot' => 3,
            'complexity_coeff' => 1,
            'surface_coeff' => 1,
            'access_coeff' => 1,
            'urgency_coeff' => 1,
            'manual_hours_delta' => 0,
            'labour_rate_minor_snapshot' => 1250,
        ]);

        self::assertSame(4167, $result['labour_subtotal_minor']);
    }

    public function testThrowsWhenNormIsInvalid(): void
    {
        $this->expectException(EstimateCalculationException::class);

        (new EstimateCalculator())->calculateLabourLine([
            'quantity' => 10,
            'norm_per_hour_snapshot' => 0,
            'labour_rate_minor_snapshot' => 1000,
        ]);
    }
}
