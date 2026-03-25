<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class PageControllerOperationalReportsTest extends TestCase
{
    protected function setUp(): void
    {
        \trn_set_test_current_user_caps(['read' => true]);
    }

    protected function tearDown(): void
    {
        \trn_reset_test_current_user_caps();
        parent::tearDown();
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
        self::assertSame('trn_view_margin', $registry['suppliers_imports']['capability']);
    }


    public function testSensitiveProcurementDataRequiresPriceAndMarginCapabilities(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $method = new ReflectionMethod($controller, 'canViewSensitiveProcurementData');
        $method->setAccessible(true);

        	\trn_set_test_current_user_caps(['read' => true, 'trn_manage_prices' => true, 'trn_view_margin' => false]);
        self::assertFalse($method->invoke($controller));

        	\trn_set_test_current_user_caps(['read' => true, 'trn_manage_prices' => true, 'trn_view_margin' => true]);
        self::assertTrue($method->invoke($controller));
    }

    public function testCanViewOperationalReportsRequiresAtLeastOneOperationalCapability(): void
    {
        $controller = new PageController(new RepositoryFactory());

        $method = new ReflectionMethod($controller, 'canViewOperationalReports');
        $method->setAccessible(true);

        \trn_set_test_current_user_caps(['read' => true]);
        self::assertFalse($method->invoke($controller));

        \trn_set_test_current_user_caps(['read' => true, 'trn_issue_invoices' => true]);
        self::assertTrue($method->invoke($controller));
    }
}
}
