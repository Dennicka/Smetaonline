<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Support;

final class WpdbStub
{
    public string $prefix = 'wp_';

    /** @var array<int, array<string, mixed>> */
    public array $updatedRows = [];

    public int $maxVersion = 0;

    public function prepare(string $query, ...$args): string
    {
        $escaped = array_map(
            static fn ($value): string => is_numeric($value) ? (string) $value : "'" . addslashes((string) $value) . "'",
            $args
        );

        return vsprintf($query, $escaped);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $where @param array<int, string> $format @param array<int, string> $whereFormat */
    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int
    {
        $this->updatedRows[] = ['table' => $table, 'data' => $data, 'where' => $where];

        return 1;
    }

    public function getVar(string $query): int
    {
        if (str_contains($query, 'MAX(version_no)')) {
            return $this->maxVersion;
        }

        return 0;
    }

    public function insert(string $table, array $data, array $format = []): int
    {
        return 1;
    }

    /**
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if ($name === 'get_var') {
            $query = (string) ($arguments[0] ?? '');

            return $this->getVar($query);
        }

        return null;
    }
}
