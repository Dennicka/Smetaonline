<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class MaterialRepository extends BaseRepository
{
    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'trn_materials';
    }

    protected function entityType(): string
    {
        return 'material';
    }

    public function create(array $data): ?int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'name_ru' => sanitize_text_field((string) ($data['name_ru'] ?? '')),
            'name_sv' => sanitize_text_field((string) ($data['name_sv'] ?? '')),
            'unit_code' => sanitize_text_field((string) ($data['unit_code'] ?? '')),
            'coverage_per_unit' => (float) ($data['coverage_per_unit'] ?? 0),
            'buy_price_minor' => (int) ($data['buy_price_minor'] ?? 0),
            'sell_price_minor' => (int) ($data['sell_price_minor'] ?? 0),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'sku' => sanitize_text_field((string) ($data['sku'] ?? '')),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%s']);
        if ($ok === false) {
            return null;
        }
        $id = (int) $wpdb->insert_id;
        if ($id > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'create', $data);
            return $id;
        }
        return null;
    }

    public function updateEntity(int $id, array $data): bool
    {
        global $wpdb;
        $updated = $wpdb->update($this->table(), [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'name_ru' => sanitize_text_field((string) ($data['name_ru'] ?? '')),
            'name_sv' => sanitize_text_field((string) ($data['name_sv'] ?? '')),
            'unit_code' => sanitize_text_field((string) ($data['unit_code'] ?? '')),
            'coverage_per_unit' => (float) ($data['coverage_per_unit'] ?? 0),
            'buy_price_minor' => (int) ($data['buy_price_minor'] ?? 0),
            'sell_price_minor' => (int) ($data['sell_price_minor'] ?? 0),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'SEK'))),
            'sku' => sanitize_text_field((string) ($data['sku'] ?? '')),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%d', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%s'], ['%d']);
        if ($updated === false) {
            return false;
        }
        if ($updated > 0) {
            $this->auditLogger->log($this->entityType(), $id, 'update', $data);
        }
        return true;
    }

    public function archive(int $id): bool
    {
        global $wpdb;
        $updated = $wpdb->update($this->table(), ['is_active' => 0, 'updated_at' => current_time('mysql', true)], ['id' => $id], ['%d', '%s'], ['%d']);
        if ($updated === false || $updated === 0) {
            return false;
        }
        $this->auditLogger->log($this->entityType(), $id, 'archive', []);
        return true;
    }
}
