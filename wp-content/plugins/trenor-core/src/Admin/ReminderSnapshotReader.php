<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class ReminderSnapshotReader
{
    /**
     * @param array<string, mixed> $reminderRow
     * @return array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>}
     */
    public function read(array $reminderRow): array
    {
        $decoded = json_decode((string) ($reminderRow['snapshot_json'] ?? ''), true);
        if (! is_array($decoded)) {
            return [
                'header' => [],
                'totals' => [],
                'lines' => [],
                'material_lines' => [],
                'metadata' => [],
            ];
        }

        return [
            'header' => is_array($decoded['header'] ?? null) && ! array_is_list($decoded['header']) ? $decoded['header'] : [],
            'totals' => is_array($decoded['totals'] ?? null) && ! array_is_list($decoded['totals']) ? $decoded['totals'] : [],
            'lines' => is_array($decoded['lines'] ?? null) && array_is_list($decoded['lines']) ? $decoded['lines'] : [],
            'material_lines' => is_array($decoded['material_lines'] ?? null) && array_is_list($decoded['material_lines']) ? $decoded['material_lines'] : [],
            'metadata' => is_array($decoded['metadata'] ?? null) && ! array_is_list($decoded['metadata']) ? $decoded['metadata'] : [],
        ];
    }
}
