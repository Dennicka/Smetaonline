<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\PaymentListSummary;

final class PaymentListSummaryTest extends TestCase
{
    private PaymentListSummary $summary;

    protected function setUp(): void
    {
        $this->summary = new PaymentListSummary();
    }

    public function testEmptyRows(): void
    {
        $actual = $this->summary->summarize([]);

        self::assertSame(0, $actual['total_rows']);
        self::assertSame(0, $actual['total_amount_minor']);
        self::assertSame(0, $actual['unique_invoice_count']);
        self::assertSame([
            'manual' => 0,
            'bank' => 0,
            'swish' => 0,
            'other' => 0,
        ], $actual['method_counts']);
    }

    public function testTotalAmountAggregation(): void
    {
        $actual = $this->summary->summarize([
            ['amount_minor' => 100],
            ['amount_minor' => '250'],
            ['amount_minor' => -25],
            ['amount_minor' => 'bad'],
        ]);

        self::assertSame(325, $actual['total_amount_minor']);
    }

    public function testUniqueInvoiceCounting(): void
    {
        $actual = $this->summary->summarize([
            ['invoice_id' => 10],
            ['invoice_id' => '10'],
            ['invoice_id' => 11],
            ['invoice_id' => 0],
            [],
        ]);

        self::assertSame(2, $actual['unique_invoice_count']);
    }

    public function testMethodBucketCounting(): void
    {
        $actual = $this->summary->summarize([
            ['method' => 'manual'],
            ['method' => 'bank'],
            ['method' => 'swish'],
            ['method' => 'MANUAL'],
        ]);

        self::assertSame(2, $actual['method_counts']['manual']);
        self::assertSame(1, $actual['method_counts']['bank']);
        self::assertSame(1, $actual['method_counts']['swish']);
        self::assertSame(0, $actual['method_counts']['other']);
    }

    public function testUnknownMethodsCountedAsOther(): void
    {
        $actual = $this->summary->summarize([
            ['method' => 'card'],
            ['method' => ''],
            [],
        ]);

        self::assertSame(3, $actual['method_counts']['other']);
    }

    public function testFilteredSubsetCounts(): void
    {
        $actual = $this->summary->summarize([
            ['invoice_id' => 10, 'amount_minor' => 100, 'method' => 'manual'],
            ['invoice_id' => 11, 'amount_minor' => 200, 'method' => 'swish'],
        ]);

        self::assertSame(2, $actual['total_rows']);
        self::assertSame(300, $actual['total_amount_minor']);
        self::assertSame(2, $actual['unique_invoice_count']);
        self::assertSame(1, $actual['method_counts']['manual']);
        self::assertSame(1, $actual['method_counts']['swish']);
    }
}
