<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

use Trenor\Core\Import\Contract\PriceImportBatchStoreInterface;

final class PriceImportBatchRepository implements PriceImportBatchStoreInterface
{
    public function create(array $data): ?int
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table(),
            [
                'supplier_id' => (int) ($data['supplier_id'] ?? 0),
                'source_name' => sanitize_text_field((string) ($data['source_name'] ?? '')),
                'source_format' => sanitize_text_field((string) ($data['source_format'] ?? 'csv')),
                'imported_at' => current_time('mysql', true),
                'imported_by_user_id' => (int) ($data['imported_by_user_id'] ?? 0),
                'status' => sanitize_text_field((string) ($data['status'] ?? 'processing')),
                'source_checksum' => sanitize_text_field((string) ($data['source_checksum'] ?? '')),
                'source_metadata_json' => (string) ($data['source_metadata_json'] ?? '{}'),
                'result_summary_json' => (string) ($data['result_summary_json'] ?? '{}'),
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return null;
        }

        return (int) $wpdb->insert_id;
    }

    public function updateStatus(int $id, string $status, array $resultSummary): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'status' => sanitize_text_field($status),
                'result_summary_json' => wp_json_encode($resultSummary) ?: '{}',
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function findCompletedByChecksum(int $supplierId, string $checksum): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE supplier_id = %d AND source_checksum = %s AND status = 'completed' ORDER BY id DESC LIMIT 1",
                $supplierId,
                $checksum
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function latest(int $limit = 20): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        ) ?: [];
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'trn_price_import_batches';
    }
}
