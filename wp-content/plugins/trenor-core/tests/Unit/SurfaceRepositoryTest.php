<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\SurfaceRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class SurfaceRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testCreateAndUpdatePersistSurfacePayload(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new SurfaceRepository();

        $id = $repository->create([
            'room_id' => '9',
            'surface_type' => 'wall',
            'length_m' => '5.5',
            'width_m' => '2.5',
            'height_m' => '2.8',
            'area_m2' => '15.4',
            'condition_state' => 'good',
            'notes' => 'Needs primer',
        ]);

        self::assertSame(1, $id);
        self::assertSame('wp_trn_surfaces', $wpdb->insertedTable);

        self::assertTrue($repository->updateEntity(1, ['room_id' => '9', 'surface_type' => 'ceiling']));
        self::assertSame('ceiling', $wpdb->updatedRows[0]['data']['surface_type']);
    }

    public function testTableAndEntityTypeMapping(): void
    {
        $repository = new SurfaceRepository();

        self::assertSame('wp_trn_surfaces', $this->invokeProtected($repository, 'table'));
        self::assertSame('surface', $this->invokeProtected($repository, 'entityType'));
    }

    private function invokeProtected(object $subject, string $method): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject);
    }
}
