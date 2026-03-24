<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoiceListSummary;

final class InvoiceListSummaryTest extends TestCase
{
    private InvoiceListSummary $summary;

    protected function setUp(): void
    {
        $this->summary = new InvoiceListSummary();
    }

    public function testEmptyRows(): void
    {
        $actual = $this->summary->summarize([]);

        self::assertSame([
            'total_rows' => 0,
            'issued' => 0,
            'partially_paid' => 0,
            'paid' => 0,
            'archived' => 0,
        ], $actual);
    }

    public function testMixedStatuses(): void
    {
        $actual = $this->summary->summarize([
            ['status' => 'issued'],
            ['status' => 'paid'],
            ['status' => 'partially_paid'],
            ['status' => 'issued'],
            ['status' => 'archived'],
        ]);

        self::assertSame(5, $actual['total_rows']);
        self::assertSame(2, $actual['issued']);
        self::assertSame(1, $actual['partially_paid']);
        self::assertSame(1, $actual['paid']);
        self::assertSame(1, $actual['archived']);
    }

    public function testFilteredSubsetCounts(): void
    {
        $actual = $this->summary->summarize([
            ['status' => 'paid'],
            ['status' => 'paid'],
        ]);

        self::assertSame(2, $actual['total_rows']);
        self::assertSame(0, $actual['issued']);
        self::assertSame(0, $actual['partially_paid']);
        self::assertSame(2, $actual['paid']);
        self::assertSame(0, $actual['archived']);
    }

    public function testUnknownAndMissingStatusesDoNotBreakCounting(): void
    {
        $actual = $this->summary->summarize([
            ['status' => 'unknown'],
            [],
            ['status' => 'ISSUED'],
        ]);

        self::assertSame(3, $actual['total_rows']);
        self::assertSame(1, $actual['issued']);
        self::assertSame(0, $actual['partially_paid']);
        self::assertSame(0, $actual['paid']);
        self::assertSame(0, $actual['archived']);
    }
}
