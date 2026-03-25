# CURRENT_ARCHITECTURE.md — Runtime architecture snapshot

## 1) Runtime contour
- System runs as WordPress plugin `trenor-core`.
- Entry point boots plugin lifecycle, autoloading, activation/deactivation hooks.
- Plugin is operationally admin-centric (wp-admin pages, form posts, repository writes).

## 2) Bootstrap and lifecycle
- `Bootstrap\Plugin`:
  - activation => migrate + register roles + store plugin version,
  - plugins_loaded => register roles, run version-gated migrations, wire admin menu/controller.
- `Bootstrap\RoleRegistrar` is source of truth for role/capability matrix.

## 3) Admin/controller layer
- `Admin\Menu` registers wp-admin pages and binds request handling.
- `Admin\PageController`:
  - central POST action dispatcher,
  - nonce + capability checks per action,
  - render methods for workspace and entity/document pages,
  - orchestrates domain services + repositories.

## 4) Data access and repositories
- `Database\RepositoryFactory` provides repositories for each contour.
- Repositories encapsulate table-level CRUD/query logic for `trn_*` schema.
- `Database\AuditLogger` records operational events.

## 5) Domain service layer
Key services:
- estimation/calculation (`EstimateCalculator`, totals calculators),
- document issuance/transitions (offert/invoice/credit note/reminder/avtal/ata services),
- numbering (`DocumentSequenceGenerator` + `DocumentSettings`),
- tax and legal modes (`TaxMode`, `ReverseChargePolicy`, ROT service),
- anti-replay/idempotency (`OperationReplayGuard`, effect fingerprint).

## 6) Document engine contour
- Document issuance stores snapshots and reference chain ids.
- PDF contour:
  - presentation assembly (`BusinessDocumentPresentationBuilder`),
  - generator (`RealPdfGenerator`),
  - artifact metadata + file persistence (`DocumentPdfArtifactService` + repository).
- Artifact storage located in uploads private-ish folder with hardening files.

## 7) Reporting / import / backup contours
- Import contour: suppliers, import batches, material supplier prices.
- Reporting contour: operational report builder + filter + CSV exporter.
- Backup contour: manifest repository + exporter + restorer with checksum validation.

## 8) Mature vs limited areas
Mature in current codebase:
- plugin-owned schema and repository pattern,
- core document chain and finance follow-ups,
- capability-gated admin routes/actions,
- backup/restore with manifest/checksum flow.

Current limitations:
- no dedicated public API layer; admin-controller centric runtime,
- no built-in migration rollback framework,
- no full-site backup/disaster-recovery orchestration,
- acceptance still relies on manual browser UAT for true runtime proof.
