# AGENTS.md — Operations Maintenance Rules

## Purpose and scope
This file defines how the next engineer/agent must work with this repository without breaking operational contours.
Scope: whole repository.

## Project shape (current reality)
- Runtime business system is a WordPress plugin: `wp-content/plugins/trenor-core`.
- Plugin bootstrap entrypoint: `wp-content/plugins/trenor-core/trenor-core.php`.
- Plugin version gate and auto-migration trigger: `Trenor\Core\Bootstrap\Plugin`.
- Most checks and test commands are executed from plugin directory, not repository root.

## Non-negotiable guardrails
Do **not** break or silently alter:
- document issuance chain (estimate -> offert/avtal -> invoice -> reminder/credit note/payment),
- numbering and sequence integrity (`trn_document_sequences`, generator semantics),
- anti-replay / business-effect idempotency (`trn_operation_tokens`, `trn_operation_receipts`),
- tax-mode behavior (private/business VAT/reverse charge and ROT restrictions),
- capability and role boundaries (`RoleRegistrar`, controller capability map),
- backup/restore behavior and confirmation semantics.

If docs/code mismatch is found, fix the smallest truthful unit (doc or minimal code inconsistency) in the same branch/PR.

## Scope discipline for changes
Allowed by default:
- documentation updates,
- focused bugfixes in touched contour,
- tests aligned with changed behavior.

Disallowed by default:
- large refactors,
- "drive-by" changes in unrelated modules,
- schema changes without migration,
- behavior changes without tests.

## Migrations discipline
- Schema changes must be introduced via `src/Database/Migrator.php` with a new migration block.
- Never rewrite historical migration names already stored in `trn_schema_migrations`.
- Activation and version-upgrade path rely on `Plugin::activate()` and `Plugin::onPluginsLoaded()`.

## Capability-sensitive areas
Before merging any admin/page/action change:
1. verify page-level capability gates in `Admin/PageController.php`,
2. verify action-level mapping in `requiredCapability()`,
3. verify role map in `Bootstrap/RoleRegistrar.php`.

No hidden privilege escalation via new form actions.

## Numbering / reference / anti-replay
- Document numbers are allocated by `DocumentSequenceGenerator` with monthly buckets (`YYYYMM`) and configurable prefixes.
- Source references (`estimate_id`, `offert_id`, `invoice_id`) must stay intact through chain transitions.
- Action handlers using replay guard must keep duplicate-safe redirects/behavior.

## Backup/restore handling rules
- Backups are plugin-owned manifests plus JSON/table snapshot and artifact bundle; not full WordPress/site backups.
- Restore is destructive for plugin-owned tables and requires explicit confirmation phrase `RESTORE <manifest_id>`.
- Never document backup as infrastructure-level DR for the full WP stack.

## Changed-set self-check before commit
Run from `wp-content/plugins/trenor-core`:
1. `composer validate --strict`
2. `composer run lint`
3. `composer run phpcs`
4. `composer run test`

Also verify:
- docs link consistency (if docs touched),
- changed files are in intended scope,
- no unrelated formatting-only churn in untouched contours.

## PR and branch rules for this repository
- One branch per task.
- One PR per task.
- If CI/checks fail, fix in same branch/PR.
- PR description must list: scope, risks, validation commands, known limitations.
