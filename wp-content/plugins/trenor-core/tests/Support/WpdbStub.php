<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Support;

final class WpdbStub
{
    public string $prefix = 'wp_';

    /** @var array<int, array<string, mixed>> */
    public array $updatedRows = [];

    public int $updateResult = 1;

    public string $insertedTable = '';

    /** @var array<int, array<string, mixed>> */
    public array $insertHistory = [];

    public int $insert_id = 1;

    public bool $failReceiptInsert = false;

    /** @var array<int, array<string, mixed>> */
    public array $receipts = [];

    public int $maxVersion = 0;

    /** @var array<int, int> */
    public array $sumByInvoice = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $paymentsByInvoice = [];

    /** @var array<int, array<string, mixed>> */
    public array $rowsById = [];

    /** @var array<int, string> */
    public array $queries = [];

    /** @var array<int, string> */
    public array $queryHistory = [];

    public bool $deleteCalled = false;

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
        $this->updatedRows[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'format' => $format,
            'where_format' => $whereFormat,
        ];

        return $this->updateResult;
    }

    public function getVar(string $query): int
    {
        if (str_contains($query, 'MAX(version_no)')) {
            return $this->maxVersion;
        }

        return 0;
    }

    public function insert(string $table, array $data, array $format = []): int|false
    {
        $this->insertedTable = $table;
        $this->insertHistory[] = [
            'table' => $table,
            'data' => $data,
            'format' => $format,
        ];

        if ($table === $this->prefix . 'trn_operation_receipts') {
            if ($this->failReceiptInsert) {
                return false;
            }

            $data['id'] = $this->insert_id;
            $this->receipts[] = $data;
        }

        if ($this->insert_id <= 0) {
            $this->insert_id = 1;
        }

        $this->insert_id++;

        return 1;
    }

    public function delete(string $table, array $where, array $format = []): int
    {
        $this->deleteCalled = true;

        return 1;
    }

    public function query(string $query): int
    {
        $this->queryHistory[] = $query;

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
            $this->queries[] = $query;

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

            if ($this->receipts !== []) {
                return $this->receipts[0];
            }

            return null;
        }

        return null;
    }
}
