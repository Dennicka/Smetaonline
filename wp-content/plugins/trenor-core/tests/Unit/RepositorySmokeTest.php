<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\MaterialRepository;
use Trenor\Core\Database\WorkItemRepository;

final class RepositorySmokeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = (object) ['prefix' => 'wp_'];
    }

    public function testWorkItemRepositoryMapsTableAndEntity(): void
    {
        $repo = new WorkItemRepository();
        $table = (new ReflectionMethod($repo, 'table'));
        $table->setAccessible(true);
        $entity = (new ReflectionMethod($repo, 'entityType'));
        $entity->setAccessible(true);

        self::assertSame('wp_trn_work_items', $table->invoke($repo));
        self::assertSame('work_item', $entity->invoke($repo));
    }

    public function testMaterialRepositoryMapsTableAndEntity(): void
    {
        $repo = new MaterialRepository();
        $table = (new ReflectionMethod($repo, 'table'));
        $table->setAccessible(true);
        $entity = (new ReflectionMethod($repo, 'entityType'));
        $entity->setAccessible(true);

        self::assertSame('wp_trn_materials', $table->invoke($repo));
        self::assertSame('material', $entity->invoke($repo));
    }
}
