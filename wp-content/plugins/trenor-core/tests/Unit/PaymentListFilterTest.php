<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\PaymentListFilter;

final class PaymentListFilterTest extends TestCase
{
    private PaymentListFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new PaymentListFilter();
    }

    public function testNoFiltersReturnsAllRowsInOriginalOrder(): void
    {
        $rows = $this->sampleRows();

        $actual = $this->filter->apply($rows, []);

        self::assertSame($rows, $actual);
    }

    public function testPaymentIdFilter(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['payment_id' => '2']);

        self::assertSame([2], array_column($actual, 'id'));
    }

    public function testInvoiceIdFilter(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['invoice_id' => '10']);

        self::assertSame([1, 3], array_column($actual, 'id'));
    }

    public function testCurrencyFilterCaseInsensitive(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['currency' => 'sek']);

        self::assertSame([1, 2], array_column($actual, 'id'));
    }

    public function testMethodFilterCaseInsensitive(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['method' => 'SWISH']);

        self::assertSame([2], array_column($actual, 'id'));
    }

    public function testReferenceSubstringMatchCaseInsensitive(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['reference' => 'alpha']);

        self::assertSame([1], array_column($actual, 'id'));
    }

    public function testPaymentDateFromInclusive(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['payment_date_from' => '2026-01-15']);

        self::assertSame([2, 3], array_column($actual, 'id'));
    }

    public function testPaymentDateToInclusive(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['payment_date_to' => '2026-01-15']);

        self::assertSame([1, 2], array_column($actual, 'id'));
    }

    public function testCombinedDateRange(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), [
            'payment_date_from' => '2026-01-11',
            'payment_date_to' => '2026-02-01',
        ]);

        self::assertSame([2], array_column($actual, 'id'));
    }

    public function testInvalidFiltersIgnored(): void
    {
        $rows = $this->sampleRows();

        $actual = $this->filter->apply($rows, [
            'payment_id' => '0',
            'invoice_id' => 'x',
            'currency' => ' ',
            'method' => null,
            'reference' => [],
            'payment_date_from' => 'no-date',
            'payment_date_to' => 'also bad',
        ]);

        self::assertSame($rows, $actual);
    }

    public function testCombinedFiltersWorkTogether(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), [
            'invoice_id' => '10',
            'currency' => 'sek',
            'method' => 'manual',
            'reference' => 'ALPHA',
            'payment_date_from' => '2026-01-10',
            'payment_date_to' => '2026-01-10',
        ]);

        self::assertSame([1], array_column($actual, 'id'));
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleRows(): array
    {
        return [
            [
                'id' => 1,
                'invoice_id' => 10,
                'currency' => 'SEK',
                'method' => 'manual',
                'reference' => 'Alpha payment',
                'payment_date' => '2026-01-10',
            ],
            [
                'id' => 2,
                'invoice_id' => 11,
                'currency' => 'sek',
                'method' => 'swish',
                'reference' => 'BETA-123',
                'payment_date' => '2026-01-15',
            ],
            [
                'id' => 3,
                'invoice_id' => 10,
                'currency' => 'EUR',
                'method' => 'bank',
                'reference' => 'Gamma transfer',
                'payment_date' => '2026-02-02',
            ],
        ];
    }
}
