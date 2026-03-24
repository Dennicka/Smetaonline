<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoiceListFilter;

final class InvoiceListFilterTest extends TestCase
{
    private InvoiceListFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new InvoiceListFilter();
    }

    public function testNoFiltersReturnsAllRowsInOriginalOrder(): void
    {
        $rows = $this->sampleRows();

        $actual = $this->filter->apply($rows, []);

        self::assertSame($rows, $actual);
    }

    public function testOffertIdFilter(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['offert_id' => '100']);

        self::assertSame([1, 3], array_column($actual, 'id'));
    }

    public function testEstimateIdFilter(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['estimate_id' => '200']);

        self::assertSame([1, 2], array_column($actual, 'id'));
    }

    public function testStatusFilter(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['status' => 'paid']);

        self::assertSame([2], array_column($actual, 'id'));
    }

    public function testDocumentNumberSubstringMatchCaseInsensitive(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), ['document_number' => ' inv-0 ']);

        self::assertSame([1, 2], array_column($actual, 'id'));
    }

    public function testInvalidFiltersIgnored(): void
    {
        $rows = $this->sampleRows();

        $actual = $this->filter->apply($rows, [
            'offert_id' => 'oops',
            'estimate_id' => '',
            'status' => 'draft',
            'document_number' => '   ',
        ]);

        self::assertSame($rows, $actual);
    }

    public function testCombinedFiltersWorkTogether(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), [
            'offert_id' => '100',
            'status' => 'issued',
            'document_number' => '001',
        ]);

        self::assertSame([1], array_column($actual, 'id'));
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleRows(): array
    {
        return [
            [
                'id' => 1,
                'offert_id' => 100,
                'estimate_id' => 200,
                'status' => 'issued',
                'document_number' => 'INV-001',
            ],
            [
                'id' => 2,
                'offert_id' => 101,
                'estimate_id' => 200,
                'status' => 'paid',
                'document_number' => 'inv-002',
            ],
            [
                'id' => 3,
                'offert_id' => 100,
                'estimate_id' => 201,
                'status' => 'archived',
                'document_number' => 'BILL-300',
            ],
        ];
    }
}
