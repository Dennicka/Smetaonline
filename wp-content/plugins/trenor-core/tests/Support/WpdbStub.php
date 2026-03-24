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

    public ?int $next_insert_id = null;

    public bool $failReceiptInsert = false;

    /** @var array<int, array<string, mixed>> */
    public array $receipts = [];

    /** @var array<int, array<string, mixed>> */
    public array $documentArtifacts = [];

    public int $maxVersion = 0;

    /** @var array<int, int> */
    public array $sumByInvoice = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $paymentsByInvoice = [];

    /** @var array<int, array<string, mixed>> */
    public array $rowsById = [];

    public bool $defaultRowByIdEnabled = true;

    /** @var array<string, mixed> */
    public array $defaultRowById = ['status' => 'issued'];

    /** @var array<int, string> */
    public array $queries = [];

    /** @var array<int, string> */
    public array $queryHistory = [];

    public bool $deleteCalled = false;

    public function prepare(string $query, ...$args): string
    {
        $escaped = array_map(
            static fn ($value): string => is_numeric($value)
                ? (string) $value
                : "'" . addslashes((string) $value) . "'",
            $args
        );

        return vsprintf($query, $escaped);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @param array<int, string> $format
     * @param array<int, string> $whereFormat
     */
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

        $assignedInsertId = $this->next_insert_id ?? $this->insert_id;
        if ($assignedInsertId <= 0) {
            $assignedInsertId = 1;
        }
        $this->insert_id = $assignedInsertId;

        if ($table === $this->prefix . 'trn_operation_receipts') {
            if ($this->failReceiptInsert) {
                return false;
            }

            $data['id'] = $assignedInsertId;
            $this->receipts[] = $data;
        }

        if ($table === $this->prefix . 'trn_document_artifacts') {
            $data['id'] = $assignedInsertId;
            $this->documentArtifacts[] = $data;
        }

        if ($this->next_insert_id !== null) {
            $this->next_insert_id = $assignedInsertId + 1;
        }

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

            if (
                str_contains($query, 'SUM(amount_minor)')
                && preg_match('/invoice_id\s*=\s*(\d+)/', $query, $matches) === 1
            ) {
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

            if (str_contains($query, $this->prefix . 'trn_document_artifacts')) {
                if (
                    preg_match("/document_type\\s*=\\s*'([^']+)'/", $query, $typeMatch) === 1
                    && preg_match('/document_id\\s*=\\s*(\\d+)/', $query, $idMatch) === 1
                    && preg_match('/version_no\\s*=\\s*(\\d+)/', $query, $versionMatch) === 1
                    && preg_match("/artifact_type\\s*=\\s*'([^']+)'/", $query, $artifactTypeMatch) === 1
                ) {
                    foreach ($this->documentArtifacts as $artifact) {
                        if (
                            (string) ($artifact['document_type'] ?? '') === $typeMatch[1]
                            && (int) ($artifact['document_id'] ?? 0) === (int) $idMatch[1]
                            && (int) ($artifact['version_no'] ?? 0) === (int) $versionMatch[1]
                            && (string) ($artifact['artifact_type'] ?? '') === $artifactTypeMatch[1]
                        ) {
                            return $artifact;
                        }
                    }
                }

                return null;
            }

            if (preg_match('/id\s*=\s*(\d+)/', $query, $matches) === 1) {
                $id = (int) $matches[1];
                if (isset($this->rowsById[$id])) {
                    return $this->rowsById[$id];
                }

                if ($this->defaultRowByIdEnabled) {
                    return array_merge(['id' => $id], $this->defaultRowById);
                }

                return null;
            }

            if (str_contains($query, $this->prefix . 'trn_operation_receipts') && $this->receipts !== []) {
                return $this->receipts[0];
            }

            return null;
        }

        return null;
    }
}
