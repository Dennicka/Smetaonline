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
        function submit_button(string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true, array $otherAttributes = []): void
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
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;
use Trenor\Core\Tests\Support\WpdbStub;

final class PageControllerUatSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        \trn_set_test_wpdb(new WpdbStub());
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_estimates' => true,
            'trn_issue_offerts' => true,
            'trn_issue_invoices' => true,
            'trn_record_payments' => true,
            'trn_issue_reminders' => true,
            'trn_issue_credit_notes' => true,
            'trn_manage_prices' => true,
            'trn_manage_backups' => true,
            'trn_manage_templates' => true,
            'trn_manage_clients' => true,
            'trn_manage_projects' => true,
            'trn_archive_records' => true,
        ]);
    }

    protected function tearDown(): void
    {
        \trn_reset_test_current_user_caps();
        parent::tearDown();
    }


    public function testClientsWorkspaceSmokeRendersOperatorShell(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderClients();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Clients workspace', $output);
        self::assertStringContainsString('Client registry with CRM adjacency links.', $output);
        self::assertStringContainsString('Next: Property', $output);
        self::assertStringContainsString('Create', $output);
    }

    public function testProjectsWorkspaceSmokeRendersProjectAdjacencySections(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $_GET['project_id'] = '10';

        ob_start();
        $controller->renderProjects();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Projects workspace', $output);
        self::assertStringContainsString('Project dossier adjacency', $output);
        self::assertStringContainsString('Open dossier', $output);
        self::assertStringContainsString('Contacts', $output);
        self::assertStringContainsString('Rooms adjacency', $output);
    }

    public function testRoomsWorkspaceSmokeRendersSurfaceAndAttachmentContext(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $_GET['room_id'] = '12';

        ob_start();
        $controller->renderRooms();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Rooms workspace', $output);
        self::assertStringContainsString('Room adjacency context', $output);
        self::assertStringContainsString('Open room context', $output);
        self::assertStringContainsString('Surfaces', $output);
        self::assertStringContainsString('Return to project workspace', $output);
    }

    public function testEstimateWorkspaceSmokeRendersHeroAndRegister(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderEstimates();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Estimate workspace', $output);
        self::assertStringContainsString('Create estimate', $output);
        self::assertStringContainsString('Estimate register', $output);
    }

    public function testDashboardRendersFinalAcceptanceGuidanceWithoutFakeAutoPassClaim(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderDashboard();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Final acceptance operator paths', $output);
        self::assertStringContainsString('manual staging evidence', $output);
        self::assertStringContainsString('only blocker fixes found in real staging/UAT', $output);
    }

    public function testOperationalReportsRouteSmokeRendersExportControls(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderOperationalReports();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Operational Reports / Export', $output);
        self::assertStringContainsString('Export CSV', $output);
    }

    public function testSuppliersImportHistoryRouteSmokeRendersSections(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderSuppliersPrices();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Suppliers / Price import', $output);
        self::assertStringContainsString('Import price list CSV', $output);
        self::assertStringContainsString('Price history (latest rows)', $output);
    }

    public function testSettingsRouteSmokeRendersBackupRestoreSection(): void
    {
        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderSettings();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Backup / Restore v1', $output);
        self::assertStringContainsString('Create backup', $output);
        self::assertStringContainsString('Backup manifests', $output);
        self::assertStringContainsString('No backups created yet.', $output);
    }

    public function testCapabilityAwareUatPathHidesRestrictedSteps(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_estimates' => false,
            'trn_issue_offerts' => false,
            'trn_issue_invoices' => false,
            'trn_record_payments' => false,
            'trn_issue_reminders' => false,
            'trn_issue_credit_notes' => false,
            'trn_manage_prices' => false,
            'trn_manage_backups' => false,
            'trn_manage_templates' => false,
        ]);

        $controller = new PageController(new RepositoryFactory());

        ob_start();
        $controller->renderDashboard();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('No visible steps for current role in this path.', $output);
        self::assertStringNotContainsString('Open backup/restore', $output);
    }

    public function testClientsWorkspaceRequiresCapability(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_clients' => false,
        ]);

        $controller = new PageController(new RepositoryFactory());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $controller->renderClients();
    }
}
}
