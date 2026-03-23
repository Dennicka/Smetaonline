<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\ValueObject\Money;

final class MoneyTest extends TestCase
{
    public function testFromFloatAndAdd(): void
    {
        $a = Money::fromFloat(100.10);
        $b = Money::fromFloat(50.05);

        $sum = $a->add($b);

        self::assertSame(15015, $sum->minor());
        self::assertSame('SEK', $sum->currency());
    }
}
