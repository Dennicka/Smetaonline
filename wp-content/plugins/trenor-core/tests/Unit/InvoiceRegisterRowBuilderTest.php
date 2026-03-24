<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoiceRegisterRowBuilder;
use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;

final class InvoiceRegisterRowBuilderTest extends TestCase
{
    private InvoiceRegisterRowBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new InvoiceRegisterRowBuilder(new InvoicePaymentSummaryCalculator());
    }

    public function testComputedFieldsDerivedFromInvoiceAndPayments(): void
    {
        $row = $this->builder->build([
            'id' => 10,
            'offert_id' => 101,
            'estimate_id' => 202,
            'document_number' => 'INV-10',
            'version_no' => 1,
            'status' => 'issued',
            'total_inc_vat_minor' => 10000,
            'issued_at' => '2026-03-24',
            'currency' => 'SEK',
        ], [
            ['amount_minor' => 2000],
            ['amount_minor' => 500],
        ]);

        self::assertSame(2500, $row['paid_total_minor']);
        self::assertSame(7500, $row['outstanding_minor']);
        self::assertSame(2, $row['payment_count']);
        self::assertSame('partially_paid', $row['computed_status']);
    }

    public function testSparseInputHandledSafely(): void
    {
        $row = $this->builder->build([], []);

        self::assertSame('', $row['id']);
        self::assertSame('', $row['document_number']);
        self::assertSame('', $row['stored_status']);
        self::assertSame(0, $row['total_inc_vat_minor']);
        self::assertSame(0, $row['paid_total_minor']);
        self::assertSame(0, $row['outstanding_minor']);
        self::assertSame(0, $row['payment_count']);
    }

    public function testPaymentCountReflectsPaymentsArrayLength(): void
    {
        $row = $this->builder->build([
            'total_inc_vat_minor' => 5000,
        ], [
            ['amount_minor' => 1000],
            ['amount_minor' => 1000],
            ['amount_minor' => 1000],
        ]);

        self::assertSame(3, $row['payment_count']);
        self::assertSame('partially_paid', $row['computed_status']);
    }
}
