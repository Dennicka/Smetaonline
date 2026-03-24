<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\DocumentSettings;

final class DocumentSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['trn_test_options'] = [];
    }

    public function testNormalizeReturnsStableShapeAndUppercasesStructuredFields(): void
    {
        $settings = new DocumentSettings();

        $result = $settings->normalize([
            'company_name' => '  ACME ',
            'iban' => ' se123 ',
            'bic' => ' ndea se ss ',
            'vat_number' => ' se5566 ',
            'offert_intro_text' => " Intro text\n",
        ]);

        self::assertSame('ACME', $result['company_name']);
        self::assertSame('SE123', $result['iban']);
        self::assertSame('NDEA SE SS', $result['bic']);
        self::assertSame('SE5566', $result['vat_number']);
        self::assertSame('Intro text', $result['offert_intro_text']);
        self::assertContains('credit_note_footer_text', array_keys($result));
    }

    public function testSaveAndGetRoundtripNormalizedSettings(): void
    {
        $settings = new DocumentSettings();
        $settings->save([
            'company_name' => ' Demo',
            'payment_terms_days' => ' 30 ',
            'invoice_footer_text' => ' Thank you ',
        ]);

        $result = $settings->get();

        self::assertSame('Demo', $result['company_name']);
        self::assertSame('30', $result['payment_terms_days']);
        self::assertSame('Thank you', $result['invoice_footer_text']);
        self::assertSame('', $result['iban']);
    }
}
