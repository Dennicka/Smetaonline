# INSTALL.md — Clean install guide (code-grounded)

## 1) What is being installed
This repository contains a WordPress project where domain runtime is the plugin:
- `wp-content/plugins/trenor-core`.

The plugin creates and manages its own `trn_*` tables through internal migrator logic during activation/version upgrade.

## 2) Prerequisites
- PHP 8.3+.
- WordPress 6.9+.
- MySQL 8.0+ or MariaDB 10.6+.
- Composer available for plugin dependency installation.

## 3) Place project in WordPress root
Clone/copy repository into WordPress root so plugin path is exactly:
- `wp-content/plugins/trenor-core`.

## 4) Install plugin dependencies
```bash
cd wp-content/plugins/trenor-core
composer install
```

Notes:
- Plugin bootstrap loads `vendor/autoload.php` if present, otherwise falls back to local `autoload.php`.

## 5) Activate plugin
In WP Admin:
1. Open **Plugins**.
2. Activate **Trenor Core**.

On activation, plugin performs:
- migration run (`Migrator::migrate()`),
- role/capability registration,
- plugin version option update (`trn_core_version`).

## 6) Baseline data behavior
During migration `002_estimate_catalog_core`, catalog seed is inserted if missing:
- base work category + work items,
- base material category + materials.

This is idempotent upsert-like behavior by lookup, not destructive reseed.

## 7) Post-install operational checks
1. Open admin menu **Smeta** (`trn_dashboard`).
2. Confirm pages load without fatal errors:
   - Workspace,
   - Estimates,
   - Offerts / Avtal / ÄTA,
   - Invoices,
   - Settings / Backup.
3. Confirm custom roles exist (Owner/Admin, Manager, Accountant, Worker, Viewer).

## 8) What counts as successful install
Install is successful when all are true:
- Plugin activates without fatal errors.
- `trn_*` schema tables exist.
- `trn_schema_migrations` contains executed migrations up to latest available in code.
- Smeta admin menu renders.

## 9) Honest limitations
- No official WP-CLI command is shipped by this plugin for migrations; migration is activation/version-triggered.
- This install guide does not cover full web-server provisioning (Nginx/Apache/SSL), only project/plugin operational setup.
