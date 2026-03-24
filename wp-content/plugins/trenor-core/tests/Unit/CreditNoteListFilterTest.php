<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\CreditNoteListFilter;

final class CreditNoteListFilterTest extends TestCase
{
    private CreditNoteListFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new CreditNoteListFilter();
    }

    public function testApplyReturnsAllRowsWhenFiltersAreNotActive(): void
    {
        $rows = $this->rows();

        self::assertSame($rows, $this->filter->apply($rows, []));
    }

    public function testApplySupportsInvoiceStatusAndDocumentNumberFilters(): void
    {
        $rows = $this->filter->apply($this->rows(), [
            'invoice_id' => '10',
            'status' => 'issued',
            'document_number' => '003',
        ]);

        self::assertSame([3], array_column($rows, 'id'));
    }

    public function testInvalidFiltersAreIgnored(): void
    {
        $rows = $this->rows();

        self::assertSame($rows, $this->filter->apply($rows, [
            'invoice_id' => '0',
            'status' => 'paid',
            'document_number' => ' ',
        ]));
    }

    public function testNormalizedForFormReturnsTrimmedStrings(): void
    {
        $normalized = $this->filter->normalizedForForm([
            'invoice_id' => ' 7 ',
            'status' => ' issued ',
            'document_number' => ' CN-1 ',
        ]);

        self::assertSame('7', $normalized['invoice_id']);
        self::assertSame('issued', $normalized['status']);
        self::assertSame('CN-1', $normalized['document_number']);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return [
            ['id' => 1, 'invoice_id' => 10, 'status' => 'issued', 'document_number' => 'CN-2026-001'],
            ['id' => 2, 'invoice_id' => 11, 'status' => 'archived', 'document_number' => 'CN-2026-002'],
            ['id' => 3, 'invoice_id' => 10, 'status' => 'issued', 'document_number' => 'CN-2026-003'],
        ];
    }
}
