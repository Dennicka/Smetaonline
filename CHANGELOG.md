# CHANGELOG.md — Milestone changelog (engineering-level)

> This project currently follows milestone-based delivery notes (not formal semver release tags in this file).

## Baseline foundation
- Core WordPress plugin bootstrap and autoloading contour established.
- Custom schema migrator introduced for plugin-owned `trn_*` tables.
- Base admin shell and workspace/menu contour added.

## CRM / object / room contour
- Client -> Property -> Project -> Room chain implemented in custom tables.
- Additional rich entities introduced for contact persons, attachments, surfaces.
- Repository/admin CRUD surfaces for CRM/object/project data established.

## Business document core + chain
- Estimate domain with lines/material lines and calculation flow.
- Offert issuance from estimate.
- Avtal issuance from offert.
- Invoice issuance from offert and payment/reminder/credit-note follow-up contours.

## Document/PDF hardening
- Document artifact table + repository support.
- PDF artifact generation with deterministic storage path and checksum metadata.
- Artifact folder hardening (`index.php`, `.htaccess`) and artifact-aware backup/restore handling.

## Role/capability hardening
- Dedicated roles (owner/admin, manager, accountant, worker, viewer).
- Capability-gated page access and action-level checks in controller.
- Margin/backup/archive-sensitive areas restricted by explicit caps.

## Numbering / sequence / reference integrity hardening
- Sequence storage per document type and period (`YYYYMM`).
- Configurable prefixes/padding via document settings.
- Anti-replay guard tokens + business effect receipts for idempotency-sensitive actions.

## Tax modes baseline
- Tax mode normalization (private, business with standard VAT, business reverse charge).
- ROT and reverse-charge conflict prevention in estimate handling.

## Suppliers / imports / reporting / backup baseline
- Supplier registry and import-batch storage with material supplier price history.
- Operational reporting and CSV export contour.
- Backup manifest, DB snapshot, artifact bundle export/restore contour.

## Workflow / app shell baseline
- Workspace dashboard with operational path guidance.
- Cross-page workflow navigation and UAT-focused operator flow hints.
- Known limitations and acceptance artifacts documented in `docs/`.
