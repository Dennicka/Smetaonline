<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\VatCalculator;
use Trenor\Core\Domain\ValueObject\Money;

final class VatCalculatorTest extends TestCase
{
    public function testVatCalculations(): void
    {
        $calculator = new VatCalculator();
        $net = Money::fromFloat(100.00);

        self::assertSame(2500, $calculator->vatPart($net, 25)->minor());
        self::assertSame(12500, $calculator->addVat($net, 25)->minor());
    }
}
