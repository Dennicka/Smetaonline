<?php

declare(strict_types=1);

namespace {
    if (! function_exists('current_user_can')) {
        function current_user_can(string $capability): bool
        {
            $caps = $GLOBALS['trn_test_caps'] ?? ['read' => true];

            return (bool) ($caps[$capability] ?? false);
        }
    }

    if (! function_exists('admin_url')) {
        function admin_url(string $path = ''): string
        {
            return '/wp-admin/' . ltrim($path, '/');
        }
    }

    if (! function_exists('add_menu_page')) {
        function add_menu_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, $callback = null, string $iconUrl = '', ?int $position = null): void
        {
            $GLOBALS['trn_test_menu_pages'][] = $menuSlug;
        }
    }

    if (! function_exists('add_submenu_page')) {
        function add_submenu_page(string $parentSlug, string $pageTitle, string $menuTitle, string $capability, string $menuSlug, $callback = null): void
        {
            $GLOBALS['trn_test_submenu_pages'][] = $menuSlug;
        }
    }
}

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\Menu;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class AdminWorkspaceShellTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['trn_test_caps'] = ['read' => true];
        $GLOBALS['trn_test_menu_pages'] = [];
        $GLOBALS['trn_test_submenu_pages'] = [];
    }

    public function testNavigationRespectsCapabilities(): void
    {
        $GLOBALS['trn_test_caps'] = [
            'read' => true,
            'trn_issue_invoices' => true,
            'trn_record_payments' => false,
            'trn_issue_offerts' => false,
            'trn_manage_templates' => false,
            'trn_manage_prices' => false,
            'trn_manage_estimates' => false,
            'trn_issue_credit_notes' => false,
            'trn_issue_reminders' => false,
            'trn_archive_records' => false,
        ];

        $controller = new PageController(new RepositoryFactory());
        $method = new ReflectionMethod($controller, 'workspaceNavigationSections');
        $method->setAccessible(true);

        $sections = $method->invoke($controller);
        $json = json_encode($sections);

        self::assertIsString($json);
        self::assertStringContainsString('trn_invoices', $json);
        self::assertStringNotContainsString('trn_payments', $json);
        self::assertStringNotContainsString('trn_settings', $json);
    }

    public function testMenuRegistersKeyModuleRoutes(): void
    {
        $menu = new Menu(new PageController(new RepositoryFactory()));
        $menu->addMenus();

        self::assertContains('trn_dashboard', $GLOBALS['trn_test_menu_pages']);
        self::assertContains('trn_estimates', $GLOBALS['trn_test_submenu_pages']);
        self::assertContains('trn_offerts', $GLOBALS['trn_test_submenu_pages']);
        self::assertContains('trn_invoices', $GLOBALS['trn_test_submenu_pages']);
        self::assertContains('trn_payments', $GLOBALS['trn_test_submenu_pages']);
        self::assertContains('trn_suppliers_prices', $GLOBALS['trn_test_submenu_pages']);
        self::assertContains('trn_settings', $GLOBALS['trn_test_submenu_pages']);
    }
}
}
