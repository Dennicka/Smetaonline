<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\CreditNoteSnapshotReader;

final class CreditNoteSnapshotReaderTest extends TestCase
{
    private CreditNoteSnapshotReader $reader;

    protected function setUp(): void
    {
        $this->reader = new CreditNoteSnapshotReader();
    }

    public function testValidSnapshotJsonReturnsNormalizedSections(): void
    {
        $row = [
            'snapshot_json' => json_encode([
                'header' => ['currency' => 'SEK'],
                'totals' => ['total_inc_vat_minor' => 150000],
                'lines' => [['id' => 1]],
                'material_lines' => [['id' => 2]],
                'metadata' => ['source_invoice_id' => 10],
            ]),
        ];

        $actual = $this->reader->read($row);

        self::assertSame(['currency' => 'SEK'], $actual['header']);
        self::assertSame(['total_inc_vat_minor' => 150000], $actual['totals']);
        self::assertSame([['id' => 1]], $actual['lines']);
        self::assertSame([['id' => 2]], $actual['material_lines']);
        self::assertSame(['source_invoice_id' => 10], $actual['metadata']);
    }

    public function testInvalidJsonReturnsEmptySections(): void
    {
        $actual = $this->reader->read(['snapshot_json' => '{invalid']);

        self::assertSame([], $actual['header']);
        self::assertSame([], $actual['totals']);
        self::assertSame([], $actual['lines']);
        self::assertSame([], $actual['material_lines']);
        self::assertSame([], $actual['metadata']);
    }

    public function testMissingSectionsNormalizeSafely(): void
    {
        $actual = $this->reader->read([
            'snapshot_json' => json_encode([
                'header' => ['title' => 'A'],
                'lines' => [['id' => 7]],
            ]),
        ]);

        self::assertSame(['title' => 'A'], $actual['header']);
        self::assertSame([], $actual['totals']);
        self::assertSame([['id' => 7]], $actual['lines']);
        self::assertSame([], $actual['material_lines']);
        self::assertSame([], $actual['metadata']);
    }

    public function testNonArraySectionsNormalizeSafely(): void
    {
        $actual = $this->reader->read([
            'snapshot_json' => json_encode([
                'header' => 'wrong',
                'totals' => 5,
                'lines' => false,
                'material_lines' => 'wrong',
                'metadata' => 1,
            ]),
        ]);

        self::assertSame([], $actual['header']);
        self::assertSame([], $actual['totals']);
        self::assertSame([], $actual['lines']);
        self::assertSame([], $actual['material_lines']);
        self::assertSame([], $actual['metadata']);
    }
}
