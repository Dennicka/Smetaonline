<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OperationalReportFilter;

final class OperationalReportFilterTest extends TestCase
{
    public function testNormalizeResolvesValidQuickPeriodWithoutManualDates(): void
    {
        $result = (new OperationalReportFilter())->normalize([
            'period' => '7d',
        ]);

        self::assertSame('7d', $result['filters']['period']);
        self::assertNotSame('', $result['filters']['date_from']);
        self::assertNotSame('', $result['filters']['date_to']);
        self::assertSame([], $result['errors']);
    }

    public function testNormalizeResolvesQuickPeriodAndClearsStaleManualDateErrors(): void
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
        self::assertSame([], $result['errors']);
    }

    public function testNormalizeValidManualRangeWithoutQuickPeriod(): void
    {
        $result = (new OperationalReportFilter())->normalize([
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-10',
        ]);

        self::assertSame('', $result['filters']['period']);
        self::assertSame('2026-02-01', $result['filters']['date_from']);
        self::assertSame('2026-02-10', $result['filters']['date_to']);
        self::assertSame([], $result['errors']);
    }

    public function testNormalizeInvalidManualRangeWithoutQuickPeriod(): void
    {
        $result = (new OperationalReportFilter())->normalize([
            'date_from' => '2026-02-10',
            'date_to' => '2026-01-10',
        ]);

        self::assertSame('', $result['filters']['date_from']);
        self::assertSame('', $result['filters']['date_to']);
        self::assertNotEmpty($result['errors']);
    }

    public function testNormalizeInvalidQuickPeriodKeepsExplicitErrorAndUsesManualDates(): void
    {
        $result = (new OperationalReportFilter())->normalize([
            'period' => 'last_14d',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-10',
        ]);

        self::assertSame('', $result['filters']['period']);
        self::assertSame('2026-03-01', $result['filters']['date_from']);
        self::assertSame('2026-03-10', $result['filters']['date_to']);
        self::assertSame(['Invalid period value.'], $result['errors']);
    }
}
