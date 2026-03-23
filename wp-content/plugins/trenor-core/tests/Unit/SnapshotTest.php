<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Support\Snapshot;

final class SnapshotTest extends TestCase
{
    public function testFreezeCreatesImmutableCopy(): void
    {
        $source = ['a' => ['b' => 'c']];
        $copy = Snapshot::freeze($source);

        $source['a']['b'] = 'changed';

        self::assertSame('c', $copy['a']['b']);
    }
}
