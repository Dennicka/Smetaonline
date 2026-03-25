<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class PageControllerPriceCapabilityTest extends TestCase
{
    public function testSupplierAndPriceImportRequireManagePricesCapability(): void
    {
        $controller = new PageController(new RepositoryFactory());

        self::assertSame('trn_manage_prices', $this->requiredCapability($controller, 'supplier', 'create'));
        self::assertSame('trn_manage_prices', $this->requiredCapability($controller, 'price_import', 'import'));
    }

    private function requiredCapability(PageController $controller, string $entity, string $action): string
    {
        $reflection = new ReflectionMethod($controller, 'requiredCapability');
        $reflection->setAccessible(true);

        return (string) $reflection->invoke($controller, $entity, $action);
    }
}
