<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return $value;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return $value;
    }
}

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\InvoicePrintRenderer;
use Trenor\Core\Admin\OffertPrintRenderer;

final class BusinessDocumentRendererSmokeTest extends TestCase
{
    public function testOffertRendererShowsBusinessTaxModeLabelInsteadOfMoney(): void
    {
        $renderer = new OffertPrintRenderer();

        ob_start();
        $renderer->render(
            ['id' => 1, 'estimate_id' => 10],
            [
                'header' => [],
                'totals' => ['tax_mode' => 'business_reverse_charge', 'total_inc_vat_minor' => 1000],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            ['document_profile' => ['company_name' => 'Seller AB']]
        );
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Reverse charge (VAT reported by buyer)', $html);
        self::assertStringNotContainsString('tax_mode', $html);
    }

    public function testInvoiceRendererShowsBusinessTaxModeLabelAndOutstanding(): void
    {
        $renderer = new InvoicePrintRenderer();

        ob_start();
        $renderer->render(
            ['id' => 2, 'offert_id' => 1],
            [
                'header' => [],
                'totals' => ['tax_mode' => 'private_consumer', 'total_inc_vat_minor' => 5000],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ],
            ['payment_summary' => ['outstanding_minor' => 5000], 'document_profile' => ['company_name' => 'Seller AB']]
        );
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Standard VAT', $html);
        self::assertStringContainsString('Outstanding amount', $html);
    }
}
