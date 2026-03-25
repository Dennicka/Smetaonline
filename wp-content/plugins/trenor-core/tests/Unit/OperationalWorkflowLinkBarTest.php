<?php

declare(strict_types=1);

namespace {
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
}

namespace Trenor\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\PageController;
use Trenor\Core\Database\RepositoryFactory;

final class OperationalWorkflowLinkBarTest extends TestCase
{
    public function testOperationalLinkBarRendersDenseActionLayoutAndSkipsInvalidRows(): void
    {
        $controller = new PageController(new RepositoryFactory());
        $method = new ReflectionMethod($controller, 'renderOperationalLinkBar');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($controller, [
            ['label' => 'Back to list', 'url' => '/wp-admin/admin.php?page=trn_estimates'],
            ['label' => '', 'url' => '/skip-invalid-empty-label'],
            ['label' => 'Skip invalid URL', 'url' => ''],
        ]);
        $output = (string) ob_get_clean();

        self::assertStringContainsString('trn-shell__panel--compact', $output);
        self::assertStringContainsString('trn-shell__actions--dense', $output);
        self::assertStringContainsString('Back to list', $output);
        self::assertStringNotContainsString('skip-invalid-empty-label', $output);
        self::assertStringNotContainsString('Skip invalid URL', $output);
    }

    public function testKeyWorkflowCrossLinkLabelsArePresentInController(): void
    {
        $controllerSource = (string) file_get_contents(__DIR__ . '/../../src/Admin/PageController.php');

        self::assertStringContainsString('Open offerts for this estimate', $controllerSource);
        self::assertStringContainsString('Open payments register', $controllerSource);
        self::assertStringContainsString('View all reminders for this invoice', $controllerSource);
        self::assertStringContainsString('Open suppliers / imports', $controllerSource);
    }
}
}
