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
}

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class PageControllerOperationalReportsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['trn_test_caps'] = ['read' => true];
    }

    public function testOperationalReportRegistryUsesCapabilityAwareMapping(): void
    {
        $controller = new PageController(new RepositoryFactory());

        $method = new ReflectionMethod($controller, 'operationalReportRegistry');
        $method->setAccessible(true);

        $registry = $method->invoke($controller);

        self::assertIsArray($registry);
        self::assertSame('trn_issue_invoices', $registry['invoices']['capability']);
        self::assertSame('trn_record_payments', $registry['payments']['capability']);
        self::assertSame('trn_issue_reminders', $registry['reminders']['capability']);
        self::assertSame('trn_manage_prices', $registry['suppliers_imports']['capability']);
    }

    public function testCanViewOperationalReportsRequiresAtLeastOneOperationalCapability(): void
    {
        $controller = new PageController(new RepositoryFactory());

        $method = new ReflectionMethod($controller, 'canViewOperationalReports');
        $method->setAccessible(true);

        $GLOBALS['trn_test_caps'] = ['read' => true];
        self::assertFalse($method->invoke($controller));

        $GLOBALS['trn_test_caps'] = ['read' => true, 'trn_issue_invoices' => true];
        self::assertTrue($method->invoke($controller));
    }
}
}
