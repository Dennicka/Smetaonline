<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class MaterialSupplierPriceRepository
{
    public function findCurrentPrice(int $supplierId, string $materialKey): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE supplier_id = %d AND material_key = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
                $supplierId,
                $materialKey
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function findLatestCurrentByMaterialKey(string $materialKey): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE material_key = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
                $materialKey
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function closeActivePrice(int $priceId, string $effectiveTo): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'effective_to' => $effectiveTo,
                'is_active' => 0,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $priceId],
            ['%s', '%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function createPrice(array $data): ?int
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table(),
            [
                'supplier_id' => (int) ($data['supplier_id'] ?? 0),
                'batch_id' => (int) ($data['batch_id'] ?? 0),
                'material_key' => sanitize_text_field((string) ($data['material_key'] ?? '')),
                'supplier_item_code' => sanitize_text_field((string) ($data['supplier_item_code'] ?? '')),
                'title' => sanitize_text_field((string) ($data['title'] ?? '')),
                'unit' => sanitize_text_field((string) ($data['unit'] ?? '')),
                'buy_price_minor' => (int) ($data['buy_price_minor'] ?? 0),
                'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
                'vat_rate_percent' => (float) ($data['vat_rate_percent'] ?? 25),
                'effective_from' => sanitize_text_field((string) ($data['effective_from'] ?? current_time('mysql', true))),
                'effective_to' => isset($data['effective_to']) ? sanitize_text_field((string) $data['effective_to']) : null,
                'is_active' => ! empty($data['is_active']) ? 1 : 0,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            return null;
        }

        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function latest(int $limit = 50): array
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

        return $wpdb->prefix . 'trn_material_supplier_prices';
    }
}
