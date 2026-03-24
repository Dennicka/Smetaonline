<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class CatalogSeeder
{
    public function seed(): void
    {
        $workCategoryId = $this->upsertWorkCategory('Базовые работы', 'Grundarbete', 100);
        $materialCategoryId = $this->upsertMaterialCategory('Базовые материалы', 'Basmaterial', 100);

        foreach ($this->workItems() as $item) {
            $this->upsertWorkItem($workCategoryId, $item);
        }

        foreach ($this->materials() as $material) {
            $this->upsertMaterial($materialCategoryId, $material);
        }
    }

    private function upsertWorkCategory(string $nameRu, string $nameSv, int $sortOrder): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_work_categories';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name internal.
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name_ru = %s LIMIT 1", $nameRu));

        if ($id > 0) {
            return $id;
        }

        $now = current_time('mysql', true);
        $wpdb->insert($table, [
            'name_ru' => $nameRu,
            'name_sv' => $nameSv,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%d', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    private function upsertMaterialCategory(string $nameRu, string $nameSv, int $sortOrder): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_material_categories';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name internal.
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name_ru = %s LIMIT 1", $nameRu));

        if ($id > 0) {
            return $id;
        }

        $now = current_time('mysql', true);
        $wpdb->insert($table, [
            'name_ru' => $nameRu,
            'name_sv' => $nameSv,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%d', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /** @param array<string, mixed> $item */
    private function upsertWorkItem(int $categoryId, array $item): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_work_items';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name internal.
        $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name_ru = %s LIMIT 1", $item['name_ru']));
        if ($existingId > 0) {
            return;
        }

        $now = current_time('mysql', true);
        $wpdb->insert($table, [
            'category_id' => $categoryId,
            'name_ru' => $item['name_ru'],
            'name_sv' => $item['name_sv'],
            'unit_code' => $item['unit_code'],
            'norm_slow_per_hour' => $item['norm_slow_per_hour'],
            'norm_medium_per_hour' => $item['norm_medium_per_hour'],
            'norm_fast_per_hour' => $item['norm_fast_per_hour'],
            'default_material_consumption_note' => $item['default_material_consumption_note'],
            'is_rot_eligible' => $item['is_rot_eligible'],
            'is_active' => 1,
            'sort_order' => $item['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%d', '%s', '%s']);
    }

    /** @param array<string, mixed> $material */
    private function upsertMaterial(int $categoryId, array $material): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_materials';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name internal.
        $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name_ru = %s LIMIT 1", $material['name_ru']));
        if ($existingId > 0) {
            return;
        }

        $now = current_time('mysql', true);
        $wpdb->insert($table, [
            'category_id' => $categoryId,
            'name_ru' => $material['name_ru'],
            'name_sv' => $material['name_sv'],
            'unit_code' => $material['unit_code'],
            'coverage_per_unit' => $material['coverage_per_unit'],
            'buy_price_minor' => $material['buy_price_minor'],
            'sell_price_minor' => $material['sell_price_minor'],
            'currency' => 'SEK',
            'sku' => $material['sku'],
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%s']);
    }

    /** @return array<int, array<string, mixed>> */
    private function workItems(): array
    {
        return [
            ['name_ru' => 'Осмотр объекта', 'name_sv' => 'Besiktning', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 25.0, 'norm_medium_per_hour' => 35.0, 'norm_fast_per_hour' => 45.0, 'default_material_consumption_note' => '', 'is_rot_eligible' => 0, 'sort_order' => 10],
            ['name_ru' => 'Укрытие пола', 'name_sv' => 'Täckning av golv', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 8.0, 'norm_medium_per_hour' => 12.0, 'norm_fast_per_hour' => 16.0, 'default_material_consumption_note' => 'Maskering och skydd', 'is_rot_eligible' => 1, 'sort_order' => 20],
            ['name_ru' => 'Грунтовка стен', 'name_sv' => 'Grundning av väggar', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 10.0, 'norm_medium_per_hour' => 14.0, 'norm_fast_per_hour' => 18.0, 'default_material_consumption_note' => 'Grundfärg', 'is_rot_eligible' => 1, 'sort_order' => 30],
            ['name_ru' => 'Шпаклёвка стен 1 слой', 'name_sv' => 'Spackling vägg 1 lager', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 4.0, 'norm_medium_per_hour' => 6.0, 'norm_fast_per_hour' => 8.0, 'default_material_consumption_note' => 'Spackel', 'is_rot_eligible' => 1, 'sort_order' => 40],
            ['name_ru' => 'Шпаклёвка стен 2 слоя', 'name_sv' => 'Spackling vägg 2 lager', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 2.5, 'norm_medium_per_hour' => 4.0, 'norm_fast_per_hour' => 5.5, 'default_material_consumption_note' => 'Spackel', 'is_rot_eligible' => 1, 'sort_order' => 50],
            ['name_ru' => 'Покраска стен 2 слоя', 'name_sv' => 'Målning vägg 2 lager', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 8.0, 'norm_medium_per_hour' => 11.0, 'norm_fast_per_hour' => 14.0, 'default_material_consumption_note' => 'Väggfärg', 'is_rot_eligible' => 1, 'sort_order' => 60],
            ['name_ru' => 'Покраска потолка 2 слоя', 'name_sv' => 'Målning tak 2 lager', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 6.0, 'norm_medium_per_hour' => 9.0, 'norm_fast_per_hour' => 12.0, 'default_material_consumption_note' => 'Takfärg', 'is_rot_eligible' => 1, 'sort_order' => 70],
            ['name_ru' => 'Снятие обоев', 'name_sv' => 'Tapetborttagning', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 3.0, 'norm_medium_per_hour' => 5.0, 'norm_fast_per_hour' => 7.0, 'default_material_consumption_note' => '', 'is_rot_eligible' => 1, 'sort_order' => 80],
            ['name_ru' => 'Поклейка обоев', 'name_sv' => 'Tapetsering', 'unit_code' => 'sqm', 'norm_slow_per_hour' => 3.5, 'norm_medium_per_hour' => 5.5, 'norm_fast_per_hour' => 7.5, 'default_material_consumption_note' => 'Tapetlim', 'is_rot_eligible' => 1, 'sort_order' => 90],
            ['name_ru' => 'Покраска плинтусов', 'name_sv' => 'Målning socklar', 'unit_code' => 'm', 'norm_slow_per_hour' => 10.0, 'norm_medium_per_hour' => 14.0, 'norm_fast_per_hour' => 18.0, 'default_material_consumption_note' => 'Snickerifärg', 'is_rot_eligible' => 1, 'sort_order' => 100],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function materials(): array
    {
        return [
            ['name_ru' => 'Грунт', 'name_sv' => 'Grundfärg', 'unit_code' => 'l', 'coverage_per_unit' => 8.0, 'buy_price_minor' => 5500, 'sell_price_minor' => 7900, 'sku' => 'MAT-GRUND'],
            ['name_ru' => 'Краска для стен', 'name_sv' => 'Väggfärg', 'unit_code' => 'l', 'coverage_per_unit' => 7.0, 'buy_price_minor' => 9000, 'sell_price_minor' => 12500, 'sku' => 'MAT-WALL-PAINT'],
            ['name_ru' => 'Краска для потолка', 'name_sv' => 'Takfärg', 'unit_code' => 'l', 'coverage_per_unit' => 7.0, 'buy_price_minor' => 9500, 'sell_price_minor' => 13000, 'sku' => 'MAT-CEIL-PAINT'],
            ['name_ru' => 'Шпаклёвка', 'name_sv' => 'Spackel', 'unit_code' => 'kg', 'coverage_per_unit' => 2.0, 'buy_price_minor' => 2500, 'sell_price_minor' => 4200, 'sku' => 'MAT-SPACKEL'],
            ['name_ru' => 'Малярная лента', 'name_sv' => 'Maskeringstejp', 'unit_code' => 'roll', 'coverage_per_unit' => 0.0, 'buy_price_minor' => 450, 'sell_price_minor' => 950, 'sku' => 'MAT-TAPE'],
            ['name_ru' => 'Укрывочная плёнка', 'name_sv' => 'Täckplast', 'unit_code' => 'sqm', 'coverage_per_unit' => 1.0, 'buy_price_minor' => 35, 'sell_price_minor' => 90, 'sku' => 'MAT-COVER'],
            ['name_ru' => 'Шлифлист', 'name_sv' => 'Slippapper', 'unit_code' => 'sheet', 'coverage_per_unit' => 0.0, 'buy_price_minor' => 120, 'sell_price_minor' => 300, 'sku' => 'MAT-SAND'],
            ['name_ru' => 'Акрил', 'name_sv' => 'Akrylfog', 'unit_code' => 'tube', 'coverage_per_unit' => 0.0, 'buy_price_minor' => 3900, 'sell_price_minor' => 5900, 'sku' => 'MAT-ACRYL'],
            ['name_ru' => 'Валик', 'name_sv' => 'Roller', 'unit_code' => 'pcs', 'coverage_per_unit' => 0.0, 'buy_price_minor' => 6500, 'sell_price_minor' => 9900, 'sku' => 'MAT-ROLLER'],
            ['name_ru' => 'Кисть', 'name_sv' => 'Pensel', 'unit_code' => 'pcs', 'coverage_per_unit' => 0.0, 'buy_price_minor' => 4200, 'sell_price_minor' => 6900, 'sku' => 'MAT-BRUSH'],
        ];
    }
}
