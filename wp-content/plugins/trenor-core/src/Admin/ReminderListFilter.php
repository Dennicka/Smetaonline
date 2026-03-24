<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class ReminderListFilter
{
    /** @param array<int, array<string,mixed>> $rows @param array<string,mixed> $raw */
    public function apply(array $rows, array $raw): array
    {
        $normalized = $this->normalizedForForm($raw);

        return array_values(array_filter($rows, static function (array $row) use ($normalized): bool {
            if ($normalized['invoice_id'] !== '' && (int) ($row['invoice_id'] ?? 0) !== (int) $normalized['invoice_id']) {
                return false;
            }

            if ($normalized['status'] !== '' && sanitize_key((string) ($row['status'] ?? '')) !== $normalized['status']) {
                return false;
            }

            if ($normalized['document_number'] !== '' && stripos((string) ($row['document_number'] ?? ''), $normalized['document_number']) === false) {
                return false;
            }

            return true;
        }));
    }

    /** @param array<string,mixed> $raw @return array{invoice_id:string,status:string,document_number:string} */
    public function normalizedForForm(array $raw): array
    {
        return [
            'invoice_id' => $this->toText($raw['invoice_id'] ?? ''),
            'status' => sanitize_key($this->toText($raw['status'] ?? '')),
            'document_number' => $this->toText($raw['document_number'] ?? ''),
        ];
    }

    private function toText(mixed $value): string
    {
        if (is_array($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
