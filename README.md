# Smetaonline Foundation (bootstrap v1 Core)

Smetaonline starts as a closed WordPress web-system with a dedicated core plugin `trenor-core`.

## Stack
- WordPress 6.9.x
- PHP 8.3+
- MySQL 8.0+ / MariaDB 10.6+

## Local development
1. Place repository into WordPress root.
2. Install plugin dependencies:
   ```bash
   cd wp-content/plugins/trenor-core
   composer install
   ```
3. Activate plugin `Trenor Core` in wp-admin.
4. Open admin menu **Smeta**.

## Checks
```bash
cd wp-content/plugins/trenor-core
composer validate
composer run lint
composer run phpcs
composer run test
```

## Foundation scope
- dedicated DB schema and migrations
- role/capabilities matrix
- admin shell pages
- CRUD for clients/properties/projects/rooms in custom tables
- audit log for create/update/archive
