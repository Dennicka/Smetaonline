# Database Schema

All tables are prefixed with `wp_` according to site prefix.

## Tables
- `trn_schema_migrations`: executed migrations registry (`migration`, `executed_at`).
- `trn_audit_log`: immutable event log (`entity_type`, `entity_id`, `action`, `actor_user_id`, `changes_json`, `created_at`).
- `trn_clients`: client master data.
- `trn_properties`: objects/properties linked to client (`client_id`).
- `trn_projects`: projects linked to property (`property_id`).
- `trn_rooms`: rooms linked to project (`project_id`).
- `trn_document_sequences`: per document type + month counters (`doc_type`, `yyyymm`, `current_value`).

## Archival model
Entities are soft-archived with `status=archived` and `archived_at` timestamp.
