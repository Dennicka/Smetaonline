<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Trenor\Core\Database\CatalogSeeder;

final class CatalogSeederBaselineTest extends TestCase
{
    public function testBaselineCatalogContainsMultipleOperationalCategories(): void
    {
        $seeder = new CatalogSeeder();
        $reflection = new ReflectionClass($seeder);

        $workCategoriesMethod = $reflection->getMethod('workCategories');
        $workCategoriesMethod->setAccessible(true);
        $workCategories = $workCategoriesMethod->invoke($seeder);

        $materialCategoriesMethod = $reflection->getMethod('materialCategories');
        $materialCategoriesMethod->setAccessible(true);
        $materialCategories = $materialCategoriesMethod->invoke($seeder);

        self::assertIsArray($workCategories);
        self::assertIsArray($materialCategories);
        self::assertGreaterThanOrEqual(3, count($workCategories));
        self::assertGreaterThanOrEqual(2, count($materialCategories));
    }
}

