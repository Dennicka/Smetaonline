<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OperationalReportFilter;

final class OperationalReportFilterTest extends TestCase
{
    public function testNormalizeResolvesQuickPeriodAndClearsDateRangeErrors(): void
    {
        $result = (new OperationalReportFilter())->normalize([
            'status' => ' issued ',
            'period' => '7d',
            'date_from' => 'bad',
            'date_to' => '2026-01-01',
        ]);

        self::assertSame('issued', $result['filters']['status']);
        self::assertSame('7d', $result['filters']['period']);
        self::assertNotSame('', $result['filters']['date_from']);
        self::assertNotSame('', $result['filters']['date_to']);
        self::assertCount(1, $result['errors']);
    }

    public function testNormalizeRejectsInvalidDateWindow(): void
    {
        $result = (new OperationalReportFilter())->normalize([
            'date_from' => '2026-02-10',
            'date_to' => '2026-01-10',
        ]);

        self::assertSame('', $result['filters']['date_from']);
        self::assertSame('', $result['filters']['date_to']);
        self::assertNotEmpty($result['errors']);
    }
}
