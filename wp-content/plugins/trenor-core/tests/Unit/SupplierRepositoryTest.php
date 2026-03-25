<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\SupplierRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class SupplierRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testCreateSupplierWritesExpectedFields(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new SupplierRepository();

        $id = $repository->create([
            'name' => 'ByggGross',
            'code' => 'BYGG-GROSS',
            'source_type' => 'wholesale',
            'country' => 'se',
            'currency' => 'sek',
            'is_active' => 1,
        ]);

        self::assertSame(1, $id);
        self::assertSame('wp_trn_suppliers', $wpdb->insertedTable);
        self::assertSame('bygg-gross', $wpdb->insertHistory[0]['data']['code']);
        self::assertSame('SEK', $wpdb->insertHistory[0]['data']['currency']);
    }

    public function testRepositoryMapsExpectedTableAndEntityType(): void
    {
        $repository = new SupplierRepository();

        self::assertSame('wp_trn_suppliers', $this->invokeProtected($repository, 'table'));
        self::assertSame('supplier', $this->invokeProtected($repository, 'entityType'));
    }

    private function invokeProtected(object $subject, string $method): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject);
    }
}
