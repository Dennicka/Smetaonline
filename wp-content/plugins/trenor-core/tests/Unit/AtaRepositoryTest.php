<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\AtaRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class AtaRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testTransitionStatusHonorsPolicy(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repo = new AtaRepository();

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'draft'];
        self::assertTrue($repo->transitionStatus(10, 'issued', 1));

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'draft'];
        self::assertFalse($repo->transitionStatus(10, 'approved', 1));
    }

    public function testNextVersionNoReturnsMaxPlusOne(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repo = new AtaRepository();

        $wpdb->maxVersion = 2;

        self::assertSame(3, $repo->nextVersionNo(99));
    }

    public function testUpdateDraftDeniedForNonDraftStatuses(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repo = new AtaRepository();

        $wpdb->rowsById[10] = ['id' => 10, 'status' => 'issued'];
        self::assertFalse($repo->updateDraft(10, ['title' => 'x']));
    }
}
