<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\AvtalListFilter;

final class AvtalListFilterTest extends TestCase
{
    public function testApplyFiltersByOffertStatusAndDocumentNumber(): void
    {
        $filter = new AvtalListFilter();

        $rows = [
            ['id' => 1, 'offert_id' => 10, 'status' => 'issued', 'document_number' => 'AVT-2026-001'],
            ['id' => 2, 'offert_id' => 10, 'status' => 'archived', 'document_number' => 'AVT-2026-002'],
            ['id' => 3, 'offert_id' => 11, 'status' => 'issued', 'document_number' => 'AVT-2026-003'],
        ];

        $actual = $filter->apply($rows, '10', 'issued', '001');

        self::assertCount(1, $actual);
        self::assertSame(1, (int) $actual[0]['id']);
    }
}
