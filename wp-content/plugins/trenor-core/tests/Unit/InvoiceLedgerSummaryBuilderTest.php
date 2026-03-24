<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoiceLedgerSummaryBuilder;

final class InvoiceLedgerSummaryBuilderTest extends TestCase
{
    private InvoiceLedgerSummaryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new InvoiceLedgerSummaryBuilder();
    }

    public function testEmptyInputReturnsZeros(): void
    {
        $actual = $this->builder->build([], []);

        self::assertSame([
            'invoice_count' => 0,
            'issued_count' => 0,
            'partially_paid_count' => 0,
            'paid_count' => 0,
            'archived_count' => 0,
            'total_invoiced_minor' => 0,
            'total_paid_minor' => 0,
            'total_outstanding_minor' => 0,
        ], $actual);
    }

    public function testSummaryCountsStatusesCorrectly(): void
    {
        $actual = $this->builder->build([
            ['id' => 1, 'status' => 'issued', 'total_inc_vat_minor' => 1000],
            ['id' => 2, 'status' => 'partially_paid', 'total_inc_vat_minor' => 2000],
            ['id' => 3, 'status' => 'paid', 'total_inc_vat_minor' => 3000],
            ['id' => 4, 'status' => 'archived', 'total_inc_vat_minor' => 4000],
        ], []);

        self::assertSame(4, $actual['invoice_count']);
        self::assertSame(1, $actual['issued_count']);
        self::assertSame(1, $actual['partially_paid_count']);
        self::assertSame(1, $actual['paid_count']);
        self::assertSame(1, $actual['archived_count']);
    }

    public function testTotalsAreComputedCorrectly(): void
    {
        $invoices = [
            ['id' => 10, 'status' => 'issued', 'total_inc_vat_minor' => 10000],
            ['id' => 11, 'status' => 'paid', 'total_inc_vat_minor' => 5000],
        ];
        $payments = [
            ['invoice_id' => 10, 'amount_minor' => 4000],
            ['invoice_id' => 10, 'amount_minor' => 500],
            ['invoice_id' => 11, 'amount_minor' => 5000],
        ];

        $actual = $this->builder->build($invoices, $payments);

        self::assertSame(15000, $actual['total_invoiced_minor']);
        self::assertSame(9500, $actual['total_paid_minor']);
        self::assertSame(5500, $actual['total_outstanding_minor']);
    }

    public function testOutstandingNeverGoesNegative(): void
    {
        $actual = $this->builder->build(
            [['id' => 1, 'status' => 'paid', 'total_inc_vat_minor' => 1000]],
            [['invoice_id' => 1, 'amount_minor' => 5000]]
        );

        self::assertSame(0, $actual['total_outstanding_minor']);
    }

    public function testArchivedInvoicesCountInTotals(): void
    {
        $actual = $this->builder->build(
            [['id' => 1, 'status' => 'archived', 'total_inc_vat_minor' => 1200]],
            [['invoice_id' => 1, 'amount_minor' => 200]]
        );

        self::assertSame(1, $actual['archived_count']);
        self::assertSame(1200, $actual['total_invoiced_minor']);
        self::assertSame(200, $actual['total_paid_minor']);
        self::assertSame(1000, $actual['total_outstanding_minor']);
    }

    public function testMissingOrNullNumericValuesHandledSafely(): void
    {
        $actual = $this->builder->build([
            ['id' => 9, 'status' => 'issued', 'total_inc_vat_minor' => null],
            ['id' => 10, 'status' => 'issued'],
        ], [
            ['invoice_id' => 9, 'amount_minor' => null],
            ['invoice_id' => null, 'amount_minor' => 100],
        ]);

        self::assertSame(2, $actual['invoice_count']);
        self::assertSame(0, $actual['total_invoiced_minor']);
        self::assertSame(0, $actual['total_paid_minor']);
        self::assertSame(0, $actual['total_outstanding_minor']);
    }
}
