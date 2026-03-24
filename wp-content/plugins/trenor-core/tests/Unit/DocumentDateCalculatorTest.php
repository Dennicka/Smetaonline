<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\DocumentDateCalculator;

final class DocumentDateCalculatorTest extends TestCase
{
    public function testAddDaysReturnsFormattedDateForValidInput(): void
    {
        $calculator = new DocumentDateCalculator();

        self::assertSame('2026-03-20', $calculator->addDays('2026-03-10 10:00:00', '10'));
    }

    public function testAddDaysReturnsEmptyStringForInvalidInput(): void
    {
        $calculator = new DocumentDateCalculator();

        self::assertSame('', $calculator->addDays('', '10'));
        self::assertSame('', $calculator->addDays('invalid date', '10'));
        self::assertSame('', $calculator->addDays('2026-03-10', '0'));
        self::assertSame('', $calculator->addDays('2026-03-10', '-2'));
        self::assertSame('', $calculator->addDays('2026-03-10', 'abc'));
    }
}
