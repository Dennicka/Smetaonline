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

        $queries = [
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

        foreach ($queries as $query) {
            dbDelta($query);
        }

        $wpdb->replace(
            $wpdb->prefix . 'trn_schema_migrations',
            [
                'migration' => '001_initial_schema',
                'executed_at' => current_time('mysql', true),
            ],
            ['%s', '%s']
        );
    }
}
