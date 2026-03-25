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

    public function testCreateSurfaceWritesExpectedBusinessInsertWithoutOrderCoupling(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new SurfaceRepository();

        $id = $repository->create([
            'room_id' => 13,
            'surface_type' => 'wall',
            'length_m' => 4.5,
            'width_m' => 2.8,
            'area_m2' => 12.6,
            'condition_state' => 'good',
            'notes' => 'Left wall',
        ]);

        self::assertSame(1, $id);

        $businessInsert = $this->findInsertByTable($wpdb->insertHistory, 'wp_trn_surfaces');
        self::assertNotNull($businessInsert);
        self::assertSame(13, $businessInsert['data']['room_id'] ?? null);
        self::assertSame('wall', $businessInsert['data']['surface_type'] ?? null);

        self::assertNotNull($this->findInsertByTable($wpdb->insertHistory, 'wp_trn_audit_log'));
    }

    public function testRepositoryMapsExpectedTableAndEntityType(): void
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

    /** @param array<int, array<string, mixed>> $insertHistory */
    private function findInsertByTable(array $insertHistory, string $table): ?array
    {
        foreach ($insertHistory as $insert) {
            if ((string) ($insert['table'] ?? '') === $table) {
                return $insert;
            }
        }

        return null;
    }
}
