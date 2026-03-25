<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OffertSnapshotReader
{
    /**
     * @param array<string, mixed> $offertRow
     * @return array{
     *     header: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     lines: array<int, mixed>,
     *     material_lines: array<int, mixed>,
     *     metadata: array<string, mixed>,
     *     rot: array<string, mixed>
     * }
     */
    public function read(array $offertRow): array
    {
        $decoded = json_decode((string) ($offertRow['snapshot_json'] ?? ''), true);
        if (! is_array($decoded)) {
            return $this->emptySnapshot();
        }

        return [
            'header' => $this->normalizeMap($decoded['header'] ?? null),
            'totals' => $this->normalizeMap($decoded['totals'] ?? null),
            'lines' => $this->normalizeList($decoded['lines'] ?? null),
            'material_lines' => $this->normalizeList($decoded['material_lines'] ?? null),
            'metadata' => $this->normalizeMap($decoded['metadata'] ?? null),
            'rot' => $this->normalizeMap($decoded['rot'] ?? null),
        ];
    }

    /**
     * @return array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>, rot: array<string, mixed>}
     */
    private function emptySnapshot(): array
    {
        return [
            'header' => [],
            'totals' => [],
            'lines' => [],
            'material_lines' => [],
            'metadata' => [],
            'rot' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeMap(mixed $value): array
    {
        return is_array($value) && array_is_list($value) === false ? $value : [];
    }

    /** @return array<int, mixed> */
    private function normalizeList(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }
}
