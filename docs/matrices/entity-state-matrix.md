# Entity State Matrix

## Entities
- Client
- Property
- Project
- Room

## States
- `active`
- `archived`

## Transitions
- `create` -> `active`
- `update` keeps current state
- `archive` -> `archived`

Every create/update/archive transition writes an entry into `trn_audit_log`.
