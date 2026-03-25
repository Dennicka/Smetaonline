<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

if (! function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return $value;
    }
}

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\AvtalDetailRenderer;
use Trenor\Core\Admin\ReminderDetailRenderer;

final class ReminderAndAvtalDetailRendererTest extends TestCase
{
    public function testReminderRendererShowsOutstandingAndProfilePaymentChannels(): void
    {
        $renderer = new ReminderDetailRenderer();

        ob_start();
        $renderer->render(
            [
                'document_number' => 'REM-202603-0001',
                'version_no' => 2,
                'invoice_id' => 55,
                'reminder_level' => 2,
                'total_inc_vat_minor' => 450000,
                'currency' => 'SEK',
            ],
            [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => ['source_invoice_document_number' => 'INV-202603-0010'],
            ],
            [
                'payment_summary' => ['outstanding_minor' => 120000, 'paid_total_minor' => 330000],
                'document_profile' => ['bankgiro' => '5555-1111'],
            ]
        );
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Source invoice document number', $html);
        self::assertStringContainsString('INV-202603-0010', $html);
        self::assertStringContainsString('Invoice outstanding', $html);
        self::assertStringContainsString('1 200.00 SEK', $html);
        self::assertStringContainsString('5555-1111', $html);
    }

    public function testAvtalRendererShowsSourceReferencesAndTotals(): void
    {
        $renderer = new AvtalDetailRenderer();

        ob_start();
        $renderer->render(
            [
                'document_number' => 'AVT-202603-0003',
                'version_no' => 1,
                'offert_id' => 17,
                'estimate_id' => 9,
                'total_inc_vat_minor' => 250000,
                'currency' => 'SEK',
            ],
            [
                'header' => [],
                'totals' => ['total_inc_vat_minor' => 250000, 'vat_minor' => 50000],
                'lines' => [],
                'material_lines' => [],
                'metadata' => ['source_offert_document_number' => 'OFF-202603-0007'],
            ],
            [
                'source_estimate' => ['title' => 'Kitchen rebuild'],
                'project' => ['name' => 'Kitchen project'],
            ]
        );
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Source offert document number', $html);
        self::assertStringContainsString('OFF-202603-0007', $html);
        self::assertStringContainsString('Project', $html);
        self::assertStringContainsString('Kitchen project', $html);
        self::assertStringContainsString('2 500.00 SEK', $html);
    }
}
