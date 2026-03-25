# BACKUP_RESTORE.md — Plugin-level backup and restore

## 1) Scope of supported backup
Implemented backup/restore is **plugin-owned scope only**:
- plugin domain tables (`trn_*` listed in exporter),
- document artifact payload files referenced from `trn_document_artifacts`.

This is **not** a full WordPress backup (core, all plugins, themes, media, server config, DB outside plugin tables).

## 2) Backup creation flow
UI path:
- `Smeta -> Settings / Backup` (`trn_settings`) -> create backup.

Internals:
- `BackupExporter` creates `trn_backup_manifests` row with `started` status,
- snapshots plugin tables into `db-snapshot.json`,
- copies artifact files into bundle directory,
- writes `artifact-manifest.json`,
- stores combined checksum and marks manifest `completed`.

Storage root:
- `wp_upload_dir()['basedir']/trenor-backups/backup-<manifest_id>/...`.

## 3) What is included
Included:
- data rows from exporter table suffix list (`trn_schema_migrations`, CRM/project/room, catalog, estimates/documents, payments, replay/receipts, artifacts metadata, suppliers/import, backup manifests, etc.),
- copied artifact files for every record in `trn_document_artifacts`.

Not included:
- WP users/options outside plugin-owned options,
- non-plugin database tables,
- theme/plugin code snapshots,
- infrastructure config.

## 4) Restore flow
UI path:
- `Smeta -> Settings / Backup`, select manifest, submit restore.

Safety requirement:
- confirmation phrase must be exact: `RESTORE <manifest_id>`.

Runtime validations:
- manifest exists and is `completed`,
- snapshot/artifact files are readable,
- checksum matches,
- artifact file checksums match manifest.

Execution behavior:
- starts DB transaction,
- deletes and re-inserts rows for each table from snapshot payload,
- copies artifact files back to their original `storage_path`,
- writes audit events for restore start/completion.

## 5) Mandatory pre/post checks
Pre-checks:
- ensure target environment has correct plugin code version,
- ensure upload filesystem is writable,
- ensure selected manifest status is `completed` and paths are readable.

Post-checks:
- open Workspace, Estimates, Invoices, Settings/Backup,
- confirm document artifact actions work for sample documents,
- confirm latest audit events include restore completion.

## 6) Operational constraints and limitations
- Restore is destructive for plugin tables (table contents replaced from snapshot).
- Restore expects destination tables to already exist.
- Backup fails if any expected plugin table is missing.
- Backup fails if an artifact referenced in DB is unreadable.
- No partial-table restore mode; restore applies whole payload.
