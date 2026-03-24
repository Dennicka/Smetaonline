<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OffertListFilter;

final class OffertListFilterTest extends TestCase
{
    private OffertListFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new OffertListFilter();
    }

    public function testNoFiltersReturnsAllRowsInOriginalOrder(): void
    {
        $rows = $this->sampleRows();

        $actual = $this->filter->apply($rows, null, null, null);

        self::assertSame($rows, $actual);
    }

    public function testEstimateIdFilterReturnsOnlyMatchingRows(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), '10', null, null);

        self::assertSame([1, 3], array_column($actual, 'id'));
    }

    public function testStatusFilterReturnsOnlyMatchingRows(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), null, 'accepted', null);

        self::assertSame([2], array_column($actual, 'id'));
    }

    public function testDocumentNumberFilterUsesCaseInsensitiveSubstringMatch(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), null, null, '  abc-  ');

        self::assertSame([1, 2], array_column($actual, 'id'));
    }

    public function testInvalidFiltersAreIgnored(): void
    {
        $rows = $this->sampleRows();

        $actual = $this->filter->apply($rows, '0', 'draft', '   ');

        self::assertSame($rows, $actual);
    }

    public function testCombinedFiltersApplyTogether(): void
    {
        $actual = $this->filter->apply($this->sampleRows(), '10', 'issued', '987');

        self::assertSame([3], array_column($actual, 'id'));
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleRows(): array
    {
        return [
            [
                'id' => 1,
                'estimate_id' => 10,
                'status' => 'issued',
                'document_number' => 'ABC-123',
            ],
            [
                'id' => 2,
                'estimate_id' => 11,
                'status' => 'accepted',
                'document_number' => 'zz-abc-777',
            ],
            [
                'id' => 3,
                'estimate_id' => 10,
                'status' => 'issued',
                'document_number' => 'OFF-987',
            ],
        ];
    }
}
