<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoiceRegisterSummaryBuilder;

final class InvoiceRegisterSummaryBuilderTest extends TestCase
{
    private InvoiceRegisterSummaryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new InvoiceRegisterSummaryBuilder();
    }

    public function testEmptyRowsReturnAllZeros(): void
    {
        $actual = $this->builder->build([]);

        self::assertSame([
            'invoices_count' => 0,
            'issued_total_minor' => 0,
            'paid_total_minor' => 0,
            'outstanding_total_minor' => 0,
            'fully_paid_count' => 0,
            'partially_paid_count' => 0,
            'archived_count' => 0,
        ], $actual);
    }

    public function testMixedRowsTotalsAndCounts(): void
    {
        $actual = $this->builder->build([
            [
                'total_inc_vat_minor' => 10000,
                'paid_total_minor' => 10000,
                'outstanding_minor' => 0,
                'computed_status' => 'paid',
                'stored_status' => 'issued',
            ],
            [
                'total_inc_vat_minor' => 8000,
                'paid_total_minor' => 3000,
                'outstanding_minor' => 5000,
                'computed_status' => 'partially_paid',
                'stored_status' => 'issued',
            ],
            [
                'total_inc_vat_minor' => 1500,
                'paid_total_minor' => 0,
                'outstanding_minor' => 1500,
                'computed_status' => 'issued',
                'stored_status' => 'archived',
            ],
        ]);

        self::assertSame(3, $actual['invoices_count']);
        self::assertSame(19500, $actual['issued_total_minor']);
        self::assertSame(13000, $actual['paid_total_minor']);
        self::assertSame(6500, $actual['outstanding_total_minor']);
        self::assertSame(1, $actual['fully_paid_count']);
        self::assertSame(1, $actual['partially_paid_count']);
        self::assertSame(1, $actual['archived_count']);
    }

    public function testOutstandingNeverBelowZeroInSummary(): void
    {
        $actual = $this->builder->build([
            ['outstanding_minor' => -500],
        ]);

        self::assertSame(0, $actual['outstanding_total_minor']);
    }
}
