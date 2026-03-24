<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Support;

final class WpdbStub
{
    public string $prefix = 'wp_';

    /** @var array<int, array<string, mixed>> */
    public array $updatedRows = [];

    public string $insertedTable = '';

    /** @var array<int, array<string, mixed>> */
    public array $insertHistory = [];

    public int $insert_id = 1;

    public int $maxVersion = 0;

    /** @var array<int, int> */
    public array $sumByInvoice = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $paymentsByInvoice = [];

    /** @var array<int, array<string, mixed>> */
    public array $rowsById = [];

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
        $this->insertedTable = $table;
        $this->insertHistory[] = [
            'table' => $table,
            'data' => $data,
            'format' => $format,
        ];

        if ($this->insert_id <= 0) {
            $this->insert_id = 1;
        }

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

            if (str_contains($query, 'SUM(amount_minor)') && preg_match('/invoice_id\s*=\s*(\d+)/', $query, $matches) === 1) {
                return $this->sumByInvoice[(int) $matches[1]] ?? 0;
            }

            return $this->getVar($query);
        }

        if ($name === 'get_results') {
            $query = (string) ($arguments[0] ?? '');

            if (preg_match('/invoice_id\s*=\s*(\d+)/', $query, $matches) === 1) {
                return $this->paymentsByInvoice[(int) $matches[1]] ?? [];
            }

            return [];
        }

        if ($name === 'get_row') {
            $query = (string) ($arguments[0] ?? '');
            if (preg_match('/id\s*=\s*(\d+)/', $query, $matches) === 1) {
                return $this->rowsById[(int) $matches[1]] ?? null;
            }

            return null;
        }

        return null;
    }
}
