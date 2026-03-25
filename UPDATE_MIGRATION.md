# UPDATE_MIGRATION.md — Update and migration runbook

## 1) Update model (current implementation)
There is no separate deploy-time migration command in plugin code.
Schema upgrades happen when WordPress loads plugins and sees plugin version increase (`trn_core_version` < `Plugin::VERSION`).

## 2) Recommended order for update
1. **Deploy code** to target environment (same plugin path).
2. **Install/update PHP deps** if needed:
   ```bash
   cd wp-content/plugins/trenor-core
   composer install --no-dev --optimize-autoloader
   ```
3. **Ensure plugin is active** in wp-admin.
4. Trigger WP admin/plugin load once (normal admin login is enough).
5. Plugin auto-runs migrations if version gate requires it.
6. Run smoke checks (section 5).

## 3) Migration mechanism details
- Activation path: `Plugin::activate()` calls `Migrator::migrate()`.
- Upgrade path: `Plugin::onPluginsLoaded()` compares stored version to `Plugin::VERSION`, then runs `Migrator::migrate()` when needed.
- Migration bookkeeping is stored in `trn_schema_migrations`.

## 4) Risks and operator cautions
- Migrations are forward-only in current plugin logic; no automated rollback/down migration flow.
- Deploying code without compatible DB state can break admin runtime.
- Partial/inconsistent deploys (code copied, dependencies missing) can cause runtime failures.

## 5) Smoke checks after update
Minimum manual checks in wp-admin:
1. Workspace (`trn_dashboard`) opens.
2. Estimates list/form opens.
3. Offerts/Avtal/ÄTA page opens.
4. Invoices page opens.
5. Settings/Backup page opens and backup manifest list renders.
6. Operational Reports page opens.

## 6) How to verify migration success
- In DB: `trn_schema_migrations` contains latest migration names expected by current `Migrator`.
- In app: no SQL errors/fatal errors on key admin pages.
- In options: `trn_core_version` equals current `Plugin::VERSION`.

## 7) Honest limitations
- No built-in dry-run migration mode.
- No built-in rollback command.
- Validation is operational (DB markers + page smoke), not a separate migration report UI.
