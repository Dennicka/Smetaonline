<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class DocumentProfileSanitizer
{
    /** @var array<int, string> */
    private const TEXT_FIELDS = [
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
    ];

    /** @var array<int, string> */
    private const NOTE_FIELDS = [
        'invoice_note',
        'offert_note',
    ];

    /** @var array<int, string> */
    private const INTEGER_FIELDS = [
        'payment_terms_days',
        'offert_valid_days',
    ];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string|int>
     */
    public function sanitize(array $input): array
    {
        $normalized = [];

        foreach (self::TEXT_FIELDS as $field) {
            $normalized[$field] = sanitize_text_field($this->scalarToString($input[$field] ?? ''));
        }

        foreach (self::NOTE_FIELDS as $field) {
            $normalized[$field] = sanitize_textarea_field($this->scalarToString($input[$field] ?? ''));
        }

        foreach (self::INTEGER_FIELDS as $field) {
            $normalized[$field] = $this->sanitizePositiveInteger($input[$field] ?? '');
        }

        return $normalized;
    }

    private function scalarToString(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function sanitizePositiveInteger(mixed $value): string|int
    {
        $raw = $this->scalarToString($value);
        if ($raw === '') {
            return '';
        }

        $number = (int) $raw;

        return $number > 0 ? $number : '';
    }
}
