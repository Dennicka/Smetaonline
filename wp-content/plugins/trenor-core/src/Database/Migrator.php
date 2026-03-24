<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class Migrator
{
    public function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $this->runQueries($this->initialSchemaQueries($charsetCollate));
        $this->markMigration('001_initial_schema');

        if (! $this->hasMigration('002_estimate_catalog_core')) {
            $this->runQueries($this->estimateCatalogQueries($charsetCollate));
            $this->markMigration('002_estimate_catalog_core');
            (new CatalogSeeder())->seed();
        }

        if (! $this->hasMigration('003_offert_core')) {
            $this->runQueries($this->offertCoreQueries($charsetCollate));
            $this->markMigration('003_offert_core');
        }

        if (! $this->hasMigration('004_invoice_core')) {
            $this->runQueries($this->invoiceCoreQueries($charsetCollate));
            $this->markMigration('004_invoice_core');
        }

        if (! $this->hasMigration('005_payment_register_core')) {
            $this->runQueries($this->paymentRegisterCoreQueries($charsetCollate));
            $this->markMigration('005_payment_register_core');
        }

        if (! $this->hasMigration('006_credit_note_core')) {
            $this->runQueries($this->creditNoteCoreQueries($charsetCollate));
            $this->markMigration('006_credit_note_core');
        }

        if (! $this->hasMigration('007_estimate_archive_and_replay_guard')) {
            $this->runQueries($this->estimateArchiveAndReplayGuardQueries($charsetCollate));
            $this->markMigration('007_estimate_archive_and_replay_guard');
        }
    }

    /** @param array<int, string> $queries */
    private function runQueries(array $queries): void
    {
        foreach ($queries as $query) {
            dbDelta($query);
        }
    }

    private function hasMigration(string $migration): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_schema_migrations';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE migration = %s LIMIT 1", $migration));

        return $exists !== null;
    }

    private function markMigration(string $migration): void
    {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'trn_schema_migrations',
            [
                'migration' => $migration,
                'executed_at' => current_time('mysql', true),
            ],
            ['%s', '%s']
        );
    }

    /** @return array<int, string> */
    private function initialSchemaQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_schema_migrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(191) NOT NULL,
                executed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY migration (migration)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(64) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                action VARCHAR(32) NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                changes_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY entity_lookup (entity_type, entity_id),
                KEY created_at (created_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                org_number VARCHAR(64) NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_properties (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                address_line VARCHAR(255) NULL,
                city VARCHAR(191) NULL,
                postal_code VARCHAR(32) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY client_id (client_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_projects (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                property_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                code VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY property_id (property_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_rooms (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                floor VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY project_id (project_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_document_sequences (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                doc_type VARCHAR(64) NOT NULL,
                yyyymm CHAR(6) NOT NULL,
                current_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY doc_period (doc_type, yyyymm)
            ) {$charsetCollate};",
        ];
    }


    /** @return array<int, string> */
    private function offertCoreQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_offerts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                estimate_id BIGINT UNSIGNED NOT NULL,
                document_number VARCHAR(64) NOT NULL,
                version_no INT NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL DEFAULT 'issued',
                currency CHAR(3) NOT NULL DEFAULT 'SEK',
                vat_rate_percent DECIMAL(8,4) NOT NULL DEFAULT 25,
                labour_total_minor BIGINT NOT NULL DEFAULT 0,
                materials_total_minor BIGINT NOT NULL DEFAULT 0,
                subtotal_ex_vat_minor BIGINT NOT NULL DEFAULT 0,
                vat_minor BIGINT NOT NULL DEFAULT 0,
                total_inc_vat_minor BIGINT NOT NULL DEFAULT 0,
                snapshot_json LONGTEXT NOT NULL,
                issued_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY document_number (document_number),
                KEY estimate_id (estimate_id),
                KEY status (status)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<int, string> */
    private function estimateCatalogQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_work_categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name_ru VARCHAR(191) NOT NULL,
                name_sv VARCHAR(191) NOT NULL,
                sort_order INT NOT NULL DEFAULT 100,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_work_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id BIGINT UNSIGNED NOT NULL,
                name_ru VARCHAR(191) NOT NULL,
                name_sv VARCHAR(191) NOT NULL,
                unit_code VARCHAR(32) NOT NULL,
                norm_slow_per_hour DECIMAL(12,4) NOT NULL DEFAULT 0,
                norm_medium_per_hour DECIMAL(12,4) NOT NULL DEFAULT 0,
                norm_fast_per_hour DECIMAL(12,4) NOT NULL DEFAULT 0,
                default_material_consumption_note TEXT NULL,
                is_rot_eligible TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY category_id (category_id),
                KEY is_active (is_active)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_material_categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name_ru VARCHAR(191) NOT NULL,
                name_sv VARCHAR(191) NOT NULL,
                sort_order INT NOT NULL DEFAULT 100,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_materials (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id BIGINT UNSIGNED NOT NULL,
                name_ru VARCHAR(191) NOT NULL,
                name_sv VARCHAR(191) NOT NULL,
                unit_code VARCHAR(32) NOT NULL,
                coverage_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0,
                buy_price_minor BIGINT NOT NULL DEFAULT 0,
                sell_price_minor BIGINT NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'SEK',
                sku VARCHAR(128) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY category_id (category_id),
                KEY is_active (is_active)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_estimates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'draft',
                currency CHAR(3) NOT NULL DEFAULT 'SEK',
                vat_rate_percent DECIMAL(8,4) NOT NULL DEFAULT 25,
                labour_rate_minor BIGINT NOT NULL DEFAULT 0,
                notes TEXT NULL,
                calculated_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY project_id (project_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_estimate_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                estimate_id BIGINT UNSIGNED NOT NULL,
                room_id BIGINT UNSIGNED NULL,
                work_item_id BIGINT UNSIGNED NULL,
                line_title_ru_snapshot VARCHAR(191) NOT NULL,
                line_title_sv_snapshot VARCHAR(191) NOT NULL,
                unit_code_snapshot VARCHAR(32) NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                speed_profile VARCHAR(16) NOT NULL DEFAULT 'medium',
                norm_per_hour_snapshot DECIMAL(12,4) NOT NULL DEFAULT 0,
                complexity_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                surface_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                access_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                urgency_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                manual_hours_delta DECIMAL(12,4) NOT NULL DEFAULT 0,
                calculated_hours DECIMAL(12,4) NOT NULL DEFAULT 0,
                labour_rate_minor_snapshot BIGINT NOT NULL DEFAULT 0,
                labour_subtotal_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY estimate_id (estimate_id),
                KEY room_id (room_id),
                KEY work_item_id (work_item_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_estimate_material_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                estimate_id BIGINT UNSIGNED NOT NULL,
                estimate_line_id BIGINT UNSIGNED NULL,
                material_id BIGINT UNSIGNED NULL,
                material_name_ru_snapshot VARCHAR(191) NOT NULL,
                material_name_sv_snapshot VARCHAR(191) NOT NULL,
                unit_code_snapshot VARCHAR(32) NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                coverage_snapshot DECIMAL(12,4) NOT NULL DEFAULT 0,
                buy_price_minor_snapshot BIGINT NOT NULL DEFAULT 0,
                sell_price_minor_snapshot BIGINT NOT NULL DEFAULT 0,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY estimate_id (estimate_id),
                KEY estimate_line_id (estimate_line_id),
                KEY material_id (material_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_estimate_snapshots (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                estimate_id BIGINT UNSIGNED NOT NULL,
                snapshot_type VARCHAR(64) NOT NULL,
                snapshot_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY estimate_id (estimate_id),
                KEY created_at (created_at)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<int, string> */
    private function creditNoteCoreQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_credit_notes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                invoice_id BIGINT UNSIGNED NOT NULL,
                offert_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                estimate_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                document_number VARCHAR(64) NOT NULL,
                version_no INT NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL DEFAULT 'issued',
                currency CHAR(3) NOT NULL DEFAULT 'SEK',
                vat_rate_percent DECIMAL(8,4) NOT NULL DEFAULT 25,
                labour_total_minor BIGINT NOT NULL DEFAULT 0,
                materials_total_minor BIGINT NOT NULL DEFAULT 0,
                subtotal_ex_vat_minor BIGINT NOT NULL DEFAULT 0,
                vat_minor BIGINT NOT NULL DEFAULT 0,
                total_inc_vat_minor BIGINT NOT NULL DEFAULT 0,
                snapshot_json LONGTEXT NOT NULL,
                issued_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY document_number (document_number),
                KEY invoice_id (invoice_id),
                KEY offert_id (offert_id),
                KEY estimate_id (estimate_id),
                KEY status (status)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<int, string> */
    private function invoiceCoreQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_invoices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                offert_id BIGINT UNSIGNED NOT NULL,
                estimate_id BIGINT UNSIGNED NOT NULL,
                document_number VARCHAR(64) NOT NULL,
                version_no INT NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL DEFAULT 'issued',
                currency CHAR(3) NOT NULL DEFAULT 'SEK',
                vat_rate_percent DECIMAL(8,4) NOT NULL DEFAULT 25,
                labour_total_minor BIGINT NOT NULL DEFAULT 0,
                materials_total_minor BIGINT NOT NULL DEFAULT 0,
                subtotal_ex_vat_minor BIGINT NOT NULL DEFAULT 0,
                vat_minor BIGINT NOT NULL DEFAULT 0,
                total_inc_vat_minor BIGINT NOT NULL DEFAULT 0,
                snapshot_json LONGTEXT NOT NULL,
                issued_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY document_number (document_number),
                KEY offert_id (offert_id),
                KEY estimate_id (estimate_id),
                KEY status (status)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<int, string> */
    private function paymentRegisterCoreQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_invoice_payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                invoice_id BIGINT UNSIGNED NOT NULL,
                payment_date DATETIME NOT NULL,
                amount_minor BIGINT NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'SEK',
                method VARCHAR(64) NOT NULL DEFAULT 'manual',
                reference VARCHAR(191) NULL,
                note TEXT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY invoice_id (invoice_id),
                KEY payment_date (payment_date),
                KEY created_at (created_at)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<int, string> */
    private function estimateArchiveAndReplayGuardQueries(string $charsetCollate): array
    {
        global $wpdb;

        return [
            "CREATE TABLE {$wpdb->prefix}trn_estimate_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                estimate_id BIGINT UNSIGNED NOT NULL,
                room_id BIGINT UNSIGNED NULL,
                work_item_id BIGINT UNSIGNED NULL,
                line_title_ru_snapshot VARCHAR(191) NOT NULL,
                line_title_sv_snapshot VARCHAR(191) NOT NULL,
                unit_code_snapshot VARCHAR(32) NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                speed_profile VARCHAR(16) NOT NULL DEFAULT 'medium',
                norm_per_hour_snapshot DECIMAL(12,4) NOT NULL DEFAULT 0,
                complexity_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                surface_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                access_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                urgency_coeff DECIMAL(10,4) NOT NULL DEFAULT 1,
                manual_hours_delta DECIMAL(12,4) NOT NULL DEFAULT 0,
                calculated_hours DECIMAL(12,4) NOT NULL DEFAULT 0,
                labour_rate_minor_snapshot BIGINT NOT NULL DEFAULT 0,
                labour_subtotal_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY estimate_id (estimate_id),
                KEY room_id (room_id),
                KEY work_item_id (work_item_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_estimate_material_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                estimate_id BIGINT UNSIGNED NOT NULL,
                estimate_line_id BIGINT UNSIGNED NULL,
                material_id BIGINT UNSIGNED NULL,
                material_name_ru_snapshot VARCHAR(191) NOT NULL,
                material_name_sv_snapshot VARCHAR(191) NOT NULL,
                unit_code_snapshot VARCHAR(32) NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                coverage_snapshot DECIMAL(12,4) NOT NULL DEFAULT 0,
                buy_price_minor_snapshot BIGINT NOT NULL DEFAULT 0,
                sell_price_minor_snapshot BIGINT NOT NULL DEFAULT 0,
                subtotal_minor BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                archived_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY estimate_id (estimate_id),
                KEY estimate_line_id (estimate_line_id),
                KEY material_id (material_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$wpdb->prefix}trn_operation_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                token VARCHAR(64) NOT NULL,
                action_name VARCHAR(64) NOT NULL,
                scope_key VARCHAR(191) NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                consumed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY token (token),
                KEY action_scope (action_name, scope_key),
                KEY consumed_at (consumed_at)
            ) {$charsetCollate};",
        ];
    }
}
