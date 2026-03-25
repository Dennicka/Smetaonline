# Capability Matrix — Sensitive Zones v2

## Scope
Business-grade visibility and action controls for procurement, margin, settings/configuration, and operational reports/exports.

## Capability-to-zone mapping

| Sensitive area | Read visibility capability | Action capability | Notes |
|---|---|---|---|
| Supplier registry | `trn_manage_prices` | `trn_manage_prices` | Registry remains visible to price operators. |
| Import batches internals | `trn_manage_prices` + `trn_view_margin` | `trn_manage_prices` + `trn_view_margin` | CSV import form, batch rows, checksum/import actor fields are hidden unless both capabilities are present. |
| Supplier price history / buy-side | `trn_manage_prices` + `trn_view_margin` | N/A (read-only table) | `buy_price_minor` is treated as procurement-sensitive data. |
| Materials catalog buy/sell split | `trn_view_margin` (buy side) | `trn_manage_catalogs` (catalog CRUD) | `buy_price_minor` is hidden when margin capability is absent. |
| Settings / templates / profiles | `trn_manage_templates` | `trn_manage_templates` | Owner/admin-level configuration surface. |
| Backup / restore section | `trn_manage_backups` | `trn_manage_backups` | Backup-only users can open settings page but only backup section is rendered. |
| Operational reports (invoices/payments/reminders) | Report-specific finance capability | Report-specific finance capability for export | Route requires at least one operational-report capability. |
| Suppliers/imports report export | `trn_view_margin` | `trn_view_margin` | Procurement-adjacent export visibility is margin-gated. |
| Operational report backup activity panel | `trn_manage_backups` | N/A | Hidden for non-backup roles. |

## Owner/Admin vs Operator visibility rules

- Owner/Admin (full capability set): sees procurement internals, margin-sensitive fields, settings/templates, backups, and all report/export blocks.
- Operator/limited role without `trn_view_margin`: can operate in non-sensitive areas but does not see procurement internals (`buy_price_minor`, import internals, price history internals).
- Backup-only role (`trn_manage_backups` without template capability): can open settings route and see only backup/restore controls; template/profile configuration remains hidden.

## Read vs action distinction

- Read visibility and action gating are intentionally separated for sensitive areas.
- Example: a role may keep access to supplier registry CRUD (`trn_manage_prices`) while import/history internals and buy-side values remain hidden unless `trn_view_margin` is also present.
- Report route access and report/export block visibility are aligned to avoid “button hidden but export route open” mismatches.
