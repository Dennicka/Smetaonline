<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\EstimateTotalsCalculator;

final class EstimateTotalsCalculatorTest extends TestCase
{
    public function testCalculatesTotalsWithVat(): void
    {
        $totals = (new EstimateTotalsCalculator())->calculate(
            [
                ['labour_subtotal_minor' => 50000],
                ['labour_subtotal_minor' => 20000],
            ],
            [
                ['subtotal_minor' => 15000],
                ['subtotal_minor' => 5000],
            ],
            25.0,
            'private_consumer',
            [
                'consumables_minor' => 2000,
                'travel_minor' => 5000,
                'waste_disposal_minor' => 1000,
                'equipment_rental_minor' => 4000,
                'other_costs_minor' => 3000,
                'margin_percent' => 10,
                'discount_minor' => 5000,
                'deposit_requested_minor' => 10000,
            ]
        );

        self::assertSame(70000, $totals['labour_total_minor']);
        self::assertSame(20000, $totals['materials_total_minor']);
        self::assertSame(15000, $totals['direct_costs_total_minor']);
        self::assertSame(105000, $totals['cost_subtotal_minor']);
        self::assertSame(10500, $totals['margin_minor']);
        self::assertSame(110500, $totals['subtotal_ex_vat_minor']);
        self::assertSame(27625, $totals['vat_minor']);
        self::assertSame(138125, $totals['total_inc_vat_minor']);
        self::assertSame(128125, $totals['outstanding_after_deposit_minor']);
    }

    public function testCalculatesReverseChargeTotalsWithoutVat(): void
    {
        $totals = (new EstimateTotalsCalculator())->calculate(
            [
                ['labour_subtotal_minor' => 50000],
                ['labour_subtotal_minor' => 20000],
            ],
            [
                ['subtotal_minor' => 15000],
                ['subtotal_minor' => 5000],
            ],
            25.0,
            'business_reverse_charge',
            ['discount_minor' => 1000]
        );

        self::assertSame(89000, $totals['subtotal_ex_vat_minor']);
        self::assertSame(0, $totals['vat_minor']);
        self::assertSame(89000, $totals['total_inc_vat_minor']);
    }
}
