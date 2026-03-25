# Staging UAT Runbook (Acceptance Evidence v1)

## 1) Start point (do this first)
1. Open **Workspace / Dashboard** (`/wp-admin/admin.php?page=trn_dashboard`).
2. Review **Release candidate readiness**:
   - resolve any items under **Required before go-live** before continuing;
   - keep **Operational warnings** as evidence (warning is not always a blocker).
3. Open **Final acceptance operator paths** and run checks in the listed order.

## 2) Manual route order (required path)
Run in this order to avoid false failures caused by missing prerequisites.

1. **First-run / setup baseline**
   - Settings (`trn_settings`): verify document issuer identity + payment terms.
   - Backup/Restore (`trn_settings`): create one backup baseline.
   - Suppliers/Imports (`trn_suppliers_prices`): verify supplier exists and at least one import batch can be reviewed.
2. **Core document chain**
   - Estimates (`trn_estimates`) -> Offerts/Avtal (`trn_offerts`) -> Invoices (`trn_invoices`).
3. **Payment closure chain**
   - Payments (`trn_payments`) -> Reminders (`trn_reminders`) and/or Credit notes (`trn_credit_notes`).
4. **Operational control evidence**
   - Operational Reports/Export (`trn_operational_reports`) and Dossier/Timeline (`trn_dossier`).

## 3) Key scenarios and expected outcomes

### A. First-run / empty-state sanity
- Dashboard shows real readiness signals (not fake “all green”).
- Empty tables provide “what to do next” guidance.
- No dead-end page without a visible next action link.

### B. Document chain
- Estimate can be created and recalculated.
- Offert can be issued from estimate.
- Avtal can be created from offert.
- Invoice can be issued from offert.
- PDF/doc artifact actions remain available where expected.

### C. Payment / reminder / credit-note chain
- Payment registration updates invoice payment state.
- Reminder creation path remains reachable from invoice-related flows.
- Credit note path remains reachable from invoice-related flows.

### D. Reporting / export
- Operational report page opens for permitted roles.
- At least one report table can be opened.
- CSV export action is present and reachable.

### E. Suppliers / import / history
- Supplier registry opens and renders.
- Import form is visible for permitted role.
- Import batch and latest price history tables render (or honest empty-state text).

### F. Backup / restore
- Backup list renders.
- Create backup action works and creates a manifest row.
- Restore action requires explicit confirmation phrase.

### G. Capability-aware visibility
- High-privilege user sees all critical UAT paths.
- Reduced-capability user sees only allowed links/forms and does not see hidden protected actions.

### H. Tax-mode sanity
- Standard VAT, ROT, and reverse-charge/B2B paths remain reachable in existing flows.
- ROT + reverse-charge conflict still blocked.

## 4) Evidence capture during UAT
For each route in section 2 capture:
- page URL/screen;
- role used;
- pass/fail status;
- short note + screenshot/log if fail;
- whether it is blocker vs non-blocker.

## 5) Exit criteria for this stage
- UAT-critical routes are reachable and understandable.
- No blocker-level acceptance surprises remain in plugin-admin contour.
- Known limitations are documented in `docs/KNOWN_LIMITATIONS.md`.
- Operator checklist is completed in `docs/ACCEPTANCE_CHECKLIST.md`.
