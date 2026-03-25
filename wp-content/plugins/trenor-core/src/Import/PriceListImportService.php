<?php

declare(strict_types=1);

namespace Trenor\Core\Import;

use Trenor\Core\Import\Contract\MaterialSupplierPriceStoreInterface;
use Trenor\Core\Import\Contract\PriceImportBatchStoreInterface;

final class PriceListImportService
{
    public function __construct(
        private readonly PriceImportBatchStoreInterface $batchRepository,
        private readonly MaterialSupplierPriceStoreInterface $priceRepository
    ) {
    }

    /** @return array<string, mixed> */
    public function importCsv(int $supplierId, string $sourceName, string $csvContent, int $userId): array
    {
        $csvContent = trim($csvContent);
        if ($csvContent === '') {
            throw new PriceImportException('Invalid file: empty content.');
        }

        $checksum = hash('sha256', $csvContent);
        $existing = $this->batchRepository->findCompletedByChecksum($supplierId, $checksum);
        if ($existing !== null) {
            return [
                'batch_id' => (int) ($existing['id'] ?? 0),
                'status' => 'duplicate',
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'invalid_rows' => 0,
                'unchanged_rows' => 0,
                'changed_rows' => 0,
                'checksum' => $checksum,
            ];
        }

        $rows = $this->parseCsvRows($csvContent);
        $summary = [
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'invalid_rows' => 0,
            'unchanged_rows' => 0,
            'changed_rows' => 0,
        ];

        $batchId = $this->batchRepository->create([
            'supplier_id' => $supplierId,
            'source_name' => $sourceName,
            'source_format' => 'csv',
            'imported_by_user_id' => $userId,
            'status' => 'processing',
            'source_checksum' => $checksum,
            'source_metadata_json' => wp_json_encode(['row_count' => count($rows)]) ?: '{}',
        ]);

        if ($batchId === null) {
            throw new PriceImportException('Import batch create failed.');
        }

        $effectiveFrom = current_time('mysql', true);

        foreach ($rows as $row) {
            if (! isset($row['material_key'], $row['buy_price_minor']) || trim((string) $row['material_key']) === '') {
                $summary['invalid_rows']++;
                continue;
            }

            $materialKey = sanitize_text_field((string) $row['material_key']);
            $buyPriceMinor = (int) $row['buy_price_minor'];
            if ($buyPriceMinor < 0) {
                $summary['invalid_rows']++;
                continue;
            }

            $current = $this->priceRepository->findCurrentPrice($supplierId, $materialKey);
            if (is_array($current) && (int) ($current['buy_price_minor'] ?? 0) === $buyPriceMinor) {
                $summary['unchanged_rows']++;
                continue;
            }

            if (is_array($current)) {
                $this->priceRepository->closeActivePrice((int) $current['id'], $effectiveFrom);
                $summary['changed_rows']++;
            }

            $created = $this->priceRepository->createPrice([
                'supplier_id' => $supplierId,
                'batch_id' => $batchId,
                'material_key' => $materialKey,
                'supplier_item_code' => (string) ($row['supplier_item_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'unit' => (string) ($row['unit'] ?? ''),
                'buy_price_minor' => $buyPriceMinor,
                'currency' => (string) ($row['currency'] ?? 'SEK'),
                'vat_rate_percent' => (float) ($row['vat_rate_percent'] ?? 25),
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => 1,
            ]);

            if ($created === null) {
                $summary['skipped_rows']++;
                continue;
            }

            $summary['imported_rows']++;
        }

        $this->batchRepository->updateStatus($batchId, 'completed', $summary + ['checksum' => $checksum]);

        return $summary + [
            'batch_id' => $batchId,
            'status' => 'completed',
            'checksum' => $checksum,
        ];
    }

    /** @return array<int, array<string, string>> */
    private function parseCsvRows(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csvContent);
        if (! is_array($lines) || count($lines) < 2) {
            throw new PriceImportException('Invalid file: expected header and at least one row.');
        }

        $header = str_getcsv((string) array_shift($lines));
        if (! is_array($header) || $header === []) {
            throw new PriceImportException('Invalid file: failed to read CSV header.');
        }

        $required = ['material_key', 'buy_price_minor'];
        foreach ($required as $requiredColumn) {
            if (! in_array($requiredColumn, $header, true)) {
                throw new PriceImportException('Unsupported format: required columns are missing.');
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            if (! is_array($values) || count($values) !== count($header)) {
                throw new PriceImportException('Broken row mapping: column count mismatch.');
            }

            /** @var array<string, string>|false $row */
            $row = array_combine($header, $values);
            if (! is_array($row)) {
                throw new PriceImportException('Broken row mapping: cannot map headers to values.');
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
