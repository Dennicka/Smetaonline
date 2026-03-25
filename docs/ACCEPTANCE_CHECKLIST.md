# Operator Acceptance Checklist (Staging UAT)

Use as a **short human checklist**. Mark each item Pass/Fail/Not applicable.

## 1) First-run / empty-state
- [ ] Dashboard readiness section is visible and understandable.
- [ ] Empty-state hints are actionable (no vague or misleading text).

## 2) Core document flow
- [ ] Estimate created/updated successfully.
- [ ] Offert issued from estimate.
- [ ] Avtal created from offert.
- [ ] Invoice issued from offert/avtal flow.

## 3) Payment closure flow
- [ ] Payment registered for invoice.
- [ ] Reminder flow reachable from invoice/debt context.
- [ ] Credit-note flow reachable from invoice context.

## 4) Reporting / export
- [ ] Operational report screen opens.
- [ ] At least one table renders correctly.
- [ ] CSV export action is available and triggers download/response.

## 5) Suppliers / import / history
- [ ] Suppliers screen opens.
- [ ] Import form is usable.
- [ ] Import batch history is visible (or clear empty-state).
- [ ] Price history section is visible (or clear empty-state).

## 6) Backup / restore
- [ ] Backup screen opens.
- [ ] Create backup action succeeds.
- [ ] Restore flow requires explicit confirmation phrase.

## 7) Capability-aware visibility
- [ ] Operator sees only allowed actions for their role.
- [ ] Protected actions are hidden/forbidden for insufficient capability.

## 8) Document / PDF artifacts
- [ ] Document print/PDF actions are reachable in key document pages.

## 9) Tax mode sanity
- [ ] Standard VAT flow remains intact.
- [ ] ROT flow remains intact.
- [ ] B2B/reverse-charge flow remains intact.
- [ ] Invalid ROT + reverse-charge combination is blocked.

## 10) Final acceptance decision
- [ ] No blocker found for go-live decision in tested scope.
- [ ] Non-blocking rough edges logged in `docs/KNOWN_LIMITATIONS.md`.
