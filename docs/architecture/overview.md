# Architecture Overview

`trenor-core` is the system-of-record layer for domain entities and avoids `wp_posts/postmeta` as storage.

## Layers
- **Bootstrap**: plugin lifecycle, versioning, role registration, migration triggering.
- **Database**: schema migrator, repositories for custom tables, audit logger.
- **Domain**: value objects and pure services (Money, VAT, sequence generator).
- **Admin**: WordPress admin shell and basic CRUD pages.

## Expansion path
Current foundation supports adding v1.1/v2 modules by introducing new migrations and repository/service classes without destructive schema resets.
