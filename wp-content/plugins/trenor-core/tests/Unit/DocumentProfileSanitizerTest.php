<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\DocumentProfileSanitizer;

final class DocumentProfileSanitizerTest extends TestCase
{
    private DocumentProfileSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new DocumentProfileSanitizer();
    }

    public function testSanitizeReturnsDeterministicShape(): void
    {
        $result = $this->sanitizer->sanitize([]);

        self::assertSame([
            'company_name',
            'org_number',
            'vat_number',
            'email',
            'phone',
            'website',
            'address_line',
            'postal_code',
            'city',
            'country',
            'bankgiro',
            'plusgiro',
            'swish',
            'iban',
            'bic',
            'invoice_note',
            'offert_note',
            'payment_terms_days',
            'offert_valid_days',
        ], array_keys($result));
    }

    public function testSanitizeTrimsAndSanitizesScalarFields(): void
    {
        $result = $this->sanitizer->sanitize([
            'company_name' => '  ACME AB  ',
            'org_number' => "\n556677-8899\t",
            'website' => ' https://example.test ',
        ]);

        self::assertSame('ACME AB', $result['company_name']);
        self::assertSame('556677-8899', $result['org_number']);
        self::assertSame('https://example.test', $result['website']);
    }

    public function testSanitizeConvertsArraysToEmptyStrings(): void
    {
        $result = $this->sanitizer->sanitize([
            'company_name' => ['invalid'],
            'invoice_note' => ['invalid'],
            'payment_terms_days' => ['30'],
        ]);

        self::assertSame('', $result['company_name']);
        self::assertSame('', $result['invoice_note']);
        self::assertSame('', $result['payment_terms_days']);
    }

    public function testSanitizeUsesTextareaPathForNotes(): void
    {
        $result = $this->sanitizer->sanitize([
            'invoice_note' => "  Line 1\nLine 2  ",
            'offert_note' => "\n  Note text\n",
        ]);

        self::assertSame("Line 1\nLine 2", $result['invoice_note']);
        self::assertSame('Note text', $result['offert_note']);
    }

    public function testSanitizeNormalizesInvalidNumericFieldsToEmptyString(): void
    {
        $result = $this->sanitizer->sanitize([
            'payment_terms_days' => '0',
            'offert_valid_days' => '-2',
        ]);

        self::assertSame('', $result['payment_terms_days']);
        self::assertSame('', $result['offert_valid_days']);
    }

    public function testSanitizePreservesPositiveIntegerFields(): void
    {
        $result = $this->sanitizer->sanitize([
            'payment_terms_days' => '30',
            'offert_valid_days' => '14',
        ]);

        self::assertSame(30, $result['payment_terms_days']);
        self::assertSame(14, $result['offert_valid_days']);
    }
}
