<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class OwnerOperatorProductivityHardeningTest extends TestCase
{
    public function testEstimateFilteringSupportsProjectStatusAndTitle(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $method = new ReflectionMethod($controller, 'filterEstimateRows');
        $method->setAccessible(true);

        $rows = [
            ['id' => 1, 'project_id' => 10, 'status' => 'draft', 'title' => 'Kitchen refresh'],
            ['id' => 2, 'project_id' => 10, 'status' => 'issued', 'title' => 'Bathroom repaint'],
            ['id' => 3, 'project_id' => 12, 'status' => 'issued', 'title' => 'Kitchen extension'],
        ];

        $filtered = $method->invoke($controller, $rows, [
            'project_id' => '10',
            'status' => 'issued',
            'title' => 'bath',
        ]);

        self::assertCount(1, $filtered);
        self::assertSame(2, (int) $filtered[0]['id']);
    }

    public function testSupplierFilteringSupportsCapabilitySafeListQueries(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $method = new ReflectionMethod($controller, 'filterSupplierRows');
        $method->setAccessible(true);

        $rows = [
            ['id' => 1, 'code' => 'ALFA', 'name' => 'Alfa Trade', 'is_active' => 1],
            ['id' => 2, 'code' => 'BETA', 'name' => 'Beta Import', 'is_active' => 0],
        ];

        $filtered = $method->invoke($controller, $rows, [
            'supplier_query' => 'alfa',
            'supplier_status' => 'active',
        ]);

        self::assertCount(1, $filtered);
        self::assertSame(1, (int) $filtered[0]['id']);
    }

    public function testControllerSourceContainsNewQuickActionAndClearFilterLinks(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../src/Admin/PageController.php');

        self::assertStringContainsString('Open reminders', $source);
        self::assertStringContainsString('Open credit notes', $source);
        self::assertStringContainsString('Clear filters', $source);
        self::assertStringContainsString('supplier (id/code/name)', $source);
    }
}
