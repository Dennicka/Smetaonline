<?php

declare(strict_types=1);

namespace {
    if (! function_exists('admin_url')) {
        function admin_url(string $path = ''): string
        {
            return '/wp-admin/' . ltrim($path, '/');
        }
    }

    if (! function_exists('esc_url')) {
        function esc_url(string $url): string
        {
            return $url;
        }
    }

    if (! function_exists('esc_html')) {
        function esc_html(string $text): string
        {
            return $text;
        }
    }

    if (! function_exists('esc_attr')) {
        function esc_attr(string $text): string
        {
            return $text;
        }
    }

    if (! function_exists('wp_nonce_field')) {
        function wp_nonce_field(string $action): void
        {
        }
    }

    if (! function_exists('submit_button')) {
        function submit_button(string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true): void
        {
            echo '<button type="submit">' . esc_html($text) . '</button>';
        }
    }

    if (! function_exists('add_query_arg')) {
        /** @param array<string, string> $args */
        function add_query_arg(array $args, string $url): string
        {
            $query = http_build_query($args);

            return $url . ($query === '' ? '' : '?' . $query);
        }
    }

    if (! function_exists('selected')) {
        function selected(string $current, string $value, bool $display = true): string
        {
            return $current === $value ? ' selected' : '';
        }
    }

    if (! function_exists('wp_die')) {
        function wp_die(string $message): void
        {
            throw new \RuntimeException($message);
        }
    }
}

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;
use Trenor\Core\Tests\Support\WpdbStub;

final class PageControllerReleaseReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        \trn_set_test_wpdb(new WpdbStub());
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_prices' => true,
            'trn_manage_templates' => true,
            'trn_manage_backups' => true,
            'trn_issue_invoices' => true,
            'trn_record_payments' => true,
            'trn_issue_reminders' => true,
            'trn_view_margin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        \trn_reset_test_current_user_caps();
        parent::tearDown();
    }

    public function testDashboardShowsReadinessSignalsAndLimitationsRegistryOnFirstRun(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderDashboard();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Release candidate readiness', $output);
        self::assertStringContainsString('Go-live blockers detected', $output);
        self::assertStringContainsString('Final acceptance operator paths', $output);
        self::assertStringContainsString('First-run / setup baseline', $output);
        self::assertStringContainsString('Go-live limitations registry', $output);
        self::assertStringContainsString('manual staging evidence', $output);
        self::assertStringContainsString('Open settings / backup', $output);
    }

    public function testSettingsScreenAllowsBackupOnlyOperatorWithoutTemplateForms(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_templates' => false,
            'trn_manage_backups' => true,
        ]);

        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderSettings();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Document profile editing is hidden for your role.', $output);
        self::assertStringContainsString('Backup / Restore', $output);
        self::assertStringNotContainsString('Save settings', $output);
        self::assertStringNotContainsString('Save document profile', $output);
    }

    public function testSuppliersPageRendersGuidedEmptyState(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderSuppliersPrices();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('No suppliers yet. Start by creating one supplier', $output);
        self::assertStringContainsString('No import batches yet.', $output);
        self::assertStringContainsString('No supplier price history yet.', $output);
    }

    public function testSuppliersPageHidesImportInternalsWithoutMarginCapability(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_prices' => true,
            'trn_view_margin' => false,
        ]);

        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderSuppliersPrices();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Import batches and supplier price history are hidden for your role.', $output);
        self::assertStringNotContainsString('Import price list CSV', $output);
        self::assertStringNotContainsString('Price history (latest rows)', $output);
    }

    public function testOperationalReportTableNoDataStateProvidesNextStepGuidance(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $method = new ReflectionMethod($controller, 'renderOperationalReportTables');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($controller, 'invoices', ['invoices' => []], [
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'period' => '',
        ]);
        $output = (string) ob_get_clean();

        self::assertStringContainsString('No rows found for current filter set. Adjust filters or start creating operational data', $output);
        self::assertStringContainsString('Export CSV', $output);
    }
}
}
