<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class DocumentProfileProvider
{
    private const OPTION_KEY = 'trn_document_profile';

    private DocumentProfileSanitizer $sanitizer;

    public function __construct(?DocumentProfileSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new DocumentProfileSanitizer();
    }

    /** @return array<string, string|int> */
    public function get(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];

        return $this->sanitizer->sanitize($stored);
    }

    /** @param array<string, mixed> $payload @return array<string, string|int> */
    public function save(array $payload): array
    {
        $normalized = $this->sanitizer->sanitize($payload);
        update_option(self::OPTION_KEY, $normalized);

        return $normalized;
    }
}
