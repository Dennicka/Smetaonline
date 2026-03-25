<?php

declare(strict_types=1);

namespace {
    if (! function_exists('wp_die')) {
        function wp_die(string $message): void
        {
            throw new \RuntimeException($message);
        }
    }
}

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class PageControllerAccessMatrixCompletionTest extends TestCase
{
    protected function tearDown(): void
    {
        \trn_reset_test_current_user_caps();
        unset($GLOBALS['trn_test_options']['trn_worker_project_scope_map']);
        parent::tearDown();
    }

    public function testDossierRouteRequiresEstimateCapabilityInsteadOfReadOnly(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_estimates' => false,
        ]);

        $controller = new PageController(new RepositoryFactory());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $controller->renderDossier();
    }

    public function testOperationalReportsRequireDedicatedCapability(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_issue_invoices' => true,
            'trn_view_operational_reports' => false,
        ]);

        $controller = new PageController(new RepositoryFactory());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $controller->renderOperationalReports();
    }

    public function testWorkerProjectScopeFiltersEstimateRows(): void
    {
        \trn_set_test_current_user_caps([
            'read' => true,
            'trn_manage_estimates' => true,
            'trn_manage_projects' => false,
            'trn_issue_offerts' => false,
        ]);
        $GLOBALS['trn_test_options']['trn_worker_project_scope_map'] = [
            0 => [22],
        ];

        $controller = new PageController(new RepositoryFactory());
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('filterEstimateRowsByProjectScope');
        $method->setAccessible(true);

        $rows = [
            ['id' => 1, 'project_id' => 22, 'title' => 'Allowed'],
            ['id' => 2, 'project_id' => 99, 'title' => 'Forbidden'],
        ];

        $filtered = $method->invoke($controller, $rows);

        self::assertCount(1, $filtered);
        self::assertSame(22, (int) $filtered[0]['project_id']);
    }
}
}
