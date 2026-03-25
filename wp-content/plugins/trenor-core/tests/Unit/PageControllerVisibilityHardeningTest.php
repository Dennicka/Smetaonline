<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class PageControllerVisibilityHardeningTest extends TestCase
{
    protected function tearDown(): void
    {
        \trn_reset_test_current_user_caps();
        parent::tearDown();
    }

    public function testMaterialsPageHidesBuyPriceColumnWithoutMarginCapability(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_catalogs' => true,
            'trn_view_margin' => false,
        ]);

        $controller = new PageController(new RepositoryFactory());
        ob_start();
        $controller->renderMaterials();
        $output = (string) ob_get_clean();

        self::assertStringNotContainsString('buy_price_minor', $output);
        self::assertStringContainsString('sell_price_minor', $output);
    }

    public function testMaterialsPageShowsBuyPriceColumnWithMarginCapability(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_catalogs' => true,
            'trn_view_margin' => true,
        ]);

        $controller = new PageController(new RepositoryFactory());
        ob_start();
        $controller->renderMaterials();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('buy_price_minor', $output);
    }
}
}
