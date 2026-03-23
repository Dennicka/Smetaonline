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
            25.0
        );

        self::assertSame(70000, $totals['labour_total_minor']);
        self::assertSame(20000, $totals['materials_total_minor']);
        self::assertSame(90000, $totals['subtotal_ex_vat_minor']);
        self::assertSame(22500, $totals['vat_minor']);
        self::assertSame(112500, $totals['total_inc_vat_minor']);
    }
}
