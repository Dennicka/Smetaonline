<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\AvtalRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class AvtalRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testTransitionStatusHonorsPolicy(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repo = new AvtalRepository();

        $wpdb->rowsById[22] = ['id' => 22, 'status' => 'issued'];
        self::assertTrue($repo->transitionStatus(22, 'archived'));

        $wpdb->rowsById[22] = ['id' => 22, 'status' => 'archived'];
        self::assertFalse($repo->transitionStatus(22, 'issued'));
        self::assertFalse($repo->transitionStatus(22, 'archived'));
    }

    public function testNextVersionNoReturnsMaxPlusOne(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repo = new AvtalRepository();

        $wpdb->maxVersion = 0;
        self::assertSame(1, $repo->nextVersionNo(7));

        $wpdb->maxVersion = 9;
        self::assertSame(10, $repo->nextVersionNo(7));
    }
}
