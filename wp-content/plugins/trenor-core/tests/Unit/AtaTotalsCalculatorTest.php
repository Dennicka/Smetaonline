<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\AtaTotalsCalculator;

final class AtaTotalsCalculatorTest extends TestCase
{
    public function testCalculatesTotalsConsistently(): void
    {
        $totals = (new AtaTotalsCalculator())->calculate(10000, 25.0, 'sek');

        self::assertSame(10000, $totals['amount_ex_vat_minor']);
        self::assertSame(25.0, $totals['vat_rate_percent']);
        self::assertSame(2500, $totals['vat_minor']);
        self::assertSame(12500, $totals['total_inc_vat_minor']);
        self::assertSame('SEK', $totals['currency']);
    }
}
