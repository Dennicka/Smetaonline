# QA_CHECKLIST.md — Manual smoke/regression checklist

Mark each item as `PASS / FAIL / N/A` and record evidence (screen URL + short note).

## 1) Install / bootstrap checks
- [ ] Plugin dependencies installed (`composer install` in plugin dir).
- [ ] Plugin activates without fatal errors.
- [ ] Smeta menu appears in wp-admin.
- [ ] Core pages open (Workspace, Estimates, Offerts, Invoices, Settings/Backup).

## 2) Update / migration checks
- [ ] After code update, plugin loads and no migration-related fatal errors appear.
- [ ] `trn_schema_migrations` contains latest migration markers from code.
- [ ] `trn_core_version` option matches plugin code version.

## 3) CRM / object / room contour
- [ ] Create/update client.
- [ ] Create/update property linked to client.
- [ ] Create/update project linked to property.
- [ ] Create/update room linked to project.
- [ ] (If in scope) create/update contact person, attachment, surface.

## 4) Business document chain
- [ ] Create estimate.
- [ ] Add estimate lines/material lines and recalculate.
- [ ] Issue offert from estimate.
- [ ] Issue avtal from offert.
- [ ] Issue invoice from offert.

## 5) Payments / reminders / credit notes
- [ ] Register invoice payment.
- [ ] Issue reminder from invoice context.
- [ ] Issue credit note from invoice context.
- [ ] Verify statuses and totals update coherently in list/detail views.

## 6) Documents / PDF
- [ ] PDF action available for supported document types.
- [ ] Artifact record created once per document version (reopen does not duplicate unexpectedly).
- [ ] Generated artifact file path resolves and file exists on filesystem.

## 7) Tax modes / pricing/import
- [ ] Standard VAT path works.
- [ ] Reverse charge path works for business mode.
- [ ] ROT + reverse-charge invalid combo is blocked.
- [ ] Supplier and price import page loads.
- [ ] Import batch and material supplier price history render.

## 8) Backup / restore
- [ ] Backup creation succeeds and manifest row appears.
- [ ] Restore requires exact confirmation phrase `RESTORE <id>`.
- [ ] Restore from completed manifest succeeds in controlled environment.
- [ ] Post-restore smoke pages open and key records are present.

## 9) Roles / capabilities
- [ ] Owner/Admin can access all critical contours.
- [ ] Manager/accountant/worker/viewer visibility matches capability map.
- [ ] Forbidden action with insufficient cap is blocked server-side.

## 10) Numbering / reference integrity / anti-replay
- [ ] Issued document numbers follow `<PREFIX>-<YYYYMM>-<SEQ>` and increment correctly.
- [ ] Estimate->offert->invoice references preserved.
- [ ] Duplicate-submit sensitive actions do not create duplicate business effects.

## 11) Operational reports / export
- [ ] Operational report page opens.
- [ ] Filters function and validation errors are understandable.
- [ ] CSV export endpoint returns expected file/response.

## 12) Regression gate before merge
Run from `wp-content/plugins/trenor-core`:
- [ ] `composer validate --strict`
- [ ] `composer run lint`
- [ ] `composer run phpcs`
- [ ] `composer run test`

## 13) Honest verification notes
- [ ] Record what was verified by code review only.
- [ ] Record what was verified by commands/tests.
- [ ] Record what still requires real staging/browser/operator UAT.


## 14) Final acceptance evidence pack linkage
- [ ] Reference `FINAL_ACCEPTANCE_PROOF_V1.md` in PR/result and keep it synced with real run evidence.
- [ ] Explicitly separate runtime-proven checks vs code/test-only checks vs not-yet-proven checks.
- [ ] Keep an honest limitations section (blocker / non-blocker / deferred).
