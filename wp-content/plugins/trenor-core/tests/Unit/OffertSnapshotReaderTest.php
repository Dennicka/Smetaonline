<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OffertSnapshotReader;

final class OffertSnapshotReaderTest extends TestCase
{
    private OffertSnapshotReader $reader;

    protected function setUp(): void
    {
        $this->reader = new OffertSnapshotReader();
    }

    public function testReadReturnsExpectedSectionsForValidSnapshotJson(): void
    {
        $offert = [
            'snapshot_json' => json_encode([
                'header' => ['currency' => 'SEK', 'title' => 'Demo'],
                'totals' => ['total_inc_vat_minor' => 1234],
                'lines' => [['id' => 10, 'quantity' => 2]],
                'material_lines' => [['id' => 20, 'quantity' => 3]],
                'metadata' => ['source_estimate_id' => 55],
            ]),
        ];

        $actual = $this->reader->read($offert);

        self::assertSame(['currency' => 'SEK', 'title' => 'Demo'], $actual['header']);
        self::assertSame(['total_inc_vat_minor' => 1234], $actual['totals']);
        self::assertSame([['id' => 10, 'quantity' => 2]], $actual['lines']);
        self::assertSame([['id' => 20, 'quantity' => 3]], $actual['material_lines']);
        self::assertSame(['source_estimate_id' => 55], $actual['metadata']);
    }

    public function testReadReturnsEmptySectionsForInvalidJson(): void
    {
        $actual = $this->reader->read(['snapshot_json' => '{invalid']);

        self::assertSame([], $actual['header']);
        self::assertSame([], $actual['totals']);
        self::assertSame([], $actual['lines']);
        self::assertSame([], $actual['material_lines']);
        self::assertSame([], $actual['metadata']);
    }

    public function testReadNormalizesMissingSectionsToEmptyArrays(): void
    {
        $offert = [
            'snapshot_json' => json_encode([
                'header' => ['currency' => 'SEK'],
                'lines' => [['id' => 10]],
            ]),
        ];

        $actual = $this->reader->read($offert);

        self::assertSame(['currency' => 'SEK'], $actual['header']);
        self::assertSame([], $actual['totals']);
        self::assertSame([['id' => 10]], $actual['lines']);
        self::assertSame([], $actual['material_lines']);
        self::assertSame([], $actual['metadata']);
    }

    public function testReadNormalizesNonArraySectionsToEmptyArrays(): void
    {
        $offert = [
            'snapshot_json' => json_encode([
                'header' => 'wrong',
                'totals' => 10,
                'lines' => 'bad',
                'material_lines' => 'bad-lines',
                'metadata' => false,
            ]),
        ];

        $actual = $this->reader->read($offert);

        self::assertSame([], $actual['header']);
        self::assertSame([], $actual['totals']);
        self::assertSame([], $actual['lines']);
        self::assertSame([], $actual['material_lines']);
        self::assertSame([], $actual['metadata']);
    }
}
