<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class DocumentSettings
{
    public const OPTION_KEY = 'trn_document_settings';

    /** @var array<int, string> */
    private const TEXT_FIELDS = [
        'company_name',
        'company_legal_name',
        'org_number',
        'vat_number',
        'email',
        'phone',
        'website',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'city',
        'country',
        'bank_name',
        'iban',
        'bic',
        'plusgiro',
        'bankgiro',
        'swish',
        'payment_terms_days',
        'sequence_prefix_offert',
        'sequence_prefix_avtal',
        'sequence_prefix_invoice',
        'sequence_prefix_reminder',
        'sequence_prefix_credit_note',
        'sequence_number_padding',
    ];

    /** @var array<int, string> */
    private const TEXTAREA_FIELDS = [
        'offert_intro_text',
        'offert_footer_text',
        'invoice_footer_text',
        'credit_note_footer_text',
    ];

    /** @return array<string, string> */
    public function get(): array
    {
        $raw = \get_option(self::OPTION_KEY, []);

        return $this->normalize(is_array($raw) ? $raw : []);
    }

    /** @param array<string, mixed> $input */
    public function save(array $input): void
    {
        \update_option(self::OPTION_KEY, $this->normalize($input));
    }

    /** @return array<int, string> */
    public function fields(): array
    {
        return array_merge(self::TEXT_FIELDS, self::TEXTAREA_FIELDS);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, string>
     */
    public function normalize(array $input): array
    {
        $normalized = [];
        foreach (self::TEXT_FIELDS as $field) {
            $normalized[$field] = $this->normalizeText($input[$field] ?? '');
        }

        foreach (self::TEXTAREA_FIELDS as $field) {
            $normalized[$field] = $this->normalizeTextarea($input[$field] ?? '');
        }

        if ($normalized['iban'] !== '') {
            $normalized['iban'] = strtoupper($normalized['iban']);
        }

        if ($normalized['bic'] !== '') {
            $normalized['bic'] = strtoupper($normalized['bic']);
        }

        if ($normalized['vat_number'] !== '') {
            $normalized['vat_number'] = strtoupper($normalized['vat_number']);
        }

        return $normalized;
    }

    private function normalizeText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(sanitize_text_field((string) $value));
    }

    public function resolveSequencePrefix(string $docType): string
    {
        $normalizedDocType = strtolower(trim($docType));
        $settings = $this->get();

        $prefixByType = [
            'off' => (string) ($settings['sequence_prefix_offert'] ?? ''),
            'avt' => (string) ($settings['sequence_prefix_avtal'] ?? ''),
            'inv' => (string) ($settings['sequence_prefix_invoice'] ?? ''),
            'rem' => (string) ($settings['sequence_prefix_reminder'] ?? ''),
            'crn' => (string) ($settings['sequence_prefix_credit_note'] ?? ''),
        ];
        $fallbackByType = [
            'off' => 'OFF',
            'avt' => 'AVT',
            'inv' => 'INV',
            'rem' => 'REM',
            'crn' => 'CRN',
        ];

        $configured = strtoupper(trim((string) ($prefixByType[$normalizedDocType] ?? '')));
        if ($configured !== '') {
            return $configured;
        }

        return (string) ($fallbackByType[$normalizedDocType] ?? strtoupper($normalizedDocType));
    }

    public function resolveSequencePadding(): int
    {
        $settings = $this->get();
        $padding = (int) ($settings['sequence_number_padding'] ?? 5);

        return max(3, min(9, $padding));
    }

    private function normalizeTextarea(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(sanitize_textarea_field((string) $value));
    }
}
