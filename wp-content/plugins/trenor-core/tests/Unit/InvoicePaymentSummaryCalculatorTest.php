<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\InvoicePaymentSummaryCalculator;

final class InvoicePaymentSummaryCalculatorTest extends TestCase
{
    public function testZeroPaymentsReturnsIssuedStatus(): void
    {
        $calculator = new InvoicePaymentSummaryCalculator();

        $summary = $calculator->calculate(['total_inc_vat_minor' => 10000], []);

        self::assertSame(10000, $summary['invoice_total_minor']);
        self::assertSame(0, $summary['paid_total_minor']);
        self::assertSame(10000, $summary['outstanding_minor']);
        self::assertSame(0, $summary['payment_count']);
        self::assertSame('issued', $summary['computed_status']);
    }

    public function testPartialPaymentsReturnsPartiallyPaidStatus(): void
    {
        $calculator = new InvoicePaymentSummaryCalculator();

        $summary = $calculator->calculate(
            ['total_inc_vat_minor' => 10000],
            [
                ['amount_minor' => 2500],
                ['amount_minor' => 1500],
            ]
        );

        self::assertSame(4000, $summary['paid_total_minor']);
        self::assertSame(6000, $summary['outstanding_minor']);
        self::assertSame(2, $summary['payment_count']);
        self::assertSame('partially_paid', $summary['computed_status']);
    }

    public function testExactFullPaymentReturnsPaidStatus(): void
    {
        $calculator = new InvoicePaymentSummaryCalculator();

        $summary = $calculator->calculate(
            ['total_inc_vat_minor' => 10000],
            [
                ['amount_minor' => 4000],
                ['amount_minor' => 6000],
            ]
        );

        self::assertSame(10000, $summary['paid_total_minor']);
        self::assertSame(0, $summary['outstanding_minor']);
        self::assertSame('paid', $summary['computed_status']);
    }

    public function testOutstandingNeverNegative(): void
    {
        $calculator = new InvoicePaymentSummaryCalculator();

        $summary = $calculator->calculate(
            ['total_inc_vat_minor' => 10000],
            [
                ['amount_minor' => 12000],
            ]
        );

        self::assertSame(0, $summary['outstanding_minor']);
    }
}
