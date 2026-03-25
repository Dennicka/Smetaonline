# DATA_MODEL.md — Current data model (plugin-owned)

All entities are stored in custom `trn_*` tables (with WP prefix applied at runtime).

## 1) System/meta tables
- `trn_schema_migrations`: executed migration markers.
- `trn_audit_log`: immutable audit events for entity/action/actor.
- `trn_backup_manifests`: backup lifecycle registry and manifest metadata.

## 2) CRM / object / room model
- `trn_clients` (customer master).
- `trn_properties` (`client_id` -> client).
- `trn_projects` (`property_id` -> property).
- `trn_rooms` (`project_id` -> project).
- Extended contour:
  - `trn_contact_persons` (client/property/project contacts),
  - `trn_attachments` (entity-bound file metadata),
  - `trn_surfaces` (room-level physical surfaces).

## 3) Catalog/pricing model
- `trn_work_categories` -> `trn_work_items`.
- `trn_material_categories` -> `trn_materials`.
- Supplier/import side:
  - `trn_suppliers`,
  - `trn_price_import_batches`,
  - `trn_material_supplier_prices` (history-like pricing records by supplier/material/import batch).

## 4) Estimate/document chain model
Core chain:
1. `trn_estimates` (root calculation entity),
2. `trn_offerts` (issued from estimate),
3. `trn_avtals` (issued from offert),
4. `trn_invoices` (issued from offert/estimate references),
5. follow-ups:
   - `trn_invoice_payments`,
   - `trn_reminders` (invoice-linked),
   - `trn_credit_notes` (invoice-linked),
   - `trn_atas` (estimate/offert linked change orders).

Estimate composition:
- `trn_estimate_lines` (work lines),
- `trn_estimate_material_lines` (material lines),
- `trn_estimate_snapshots` (immutable-ish snapshots/history).

## 5) Numbering / sequence / integrity tables
- `trn_document_sequences`:
  - key: `(doc_type, yyyymm)`,
  - holds incremental counter for document number generation.
- `trn_operation_tokens`:
  - one-time action tokens for replay protection.
- `trn_operation_receipts`:
  - idempotency receipts keyed by action/scope/effect hash.

## 6) Document artifact model
- `trn_document_artifacts`:
  - links document type/id/version to artifact metadata,
  - stores artifact type, storage path, checksum, size, mime,
  - used by PDF generation and backup artifact bundling.

## 7) Soft-archive/status approach
Many business tables use `status` fields and archive timestamps instead of hard delete from application flows.
Restore flow can still replace table content from backup payload (destructive rewrite at table scope).

## 8) Relationship intent summary
- CRM chain anchors business context for estimates/documents.
- Document chain preserves source references between stages.
- Finance tables (payments/reminders/credit notes) close invoice lifecycle.
- Import/pricing tables feed material price intelligence.
- Backup manifests + artifacts create operational recovery evidence for plugin-owned data.
