# Roles and Permissions Matrix

## Roles
- `trn_owner_admin` (Owner/Admin): full capabilities.
- `trn_manager` (Manager): full operational scope except backups.
- `trn_worker` (Worker): estimate/template execution scope, no master-data management.
- `trn_viewer_accountant` (Viewer/Accountant): invoice/payment visibility and accounting actions only.

## Capabilities
- `trn_manage_clients`
- `trn_manage_projects`
- `trn_manage_estimates`
- `trn_issue_offerts`
- `trn_issue_invoices`
- `trn_record_payments`
- `trn_manage_catalogs`
- `trn_manage_prices`
- `trn_manage_templates`
- `trn_view_margin`
- `trn_archive_records`
- `trn_manage_backups`

Administrator role is also granted all capabilities during bootstrap.
