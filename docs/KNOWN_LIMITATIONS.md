# Known Limitations Registry (Acceptance Evidence v1)

## 1) True blockers found during this engineering stage
- **None found in code-level smoke scope for this stage.**
- Note: live browser/staging data-volume checks are still required before final sign-off.

## 2) Non-blocking rough edges
- Table-heavy admin pages remain intentionally utilitarian; advanced UX polish is deferred.
- Operational wording focuses on honesty over “green” marketing language; this may look stricter but avoids false readiness.

## 3) Deferred nice-to-have improvements
- Broader UX consistency polish across all admin tables/forms.
- Additional analytics/observability automation outside current plugin runtime scope.

## 4) Requires live staging/manual confirmation only
- End-to-end operator speed/clarity with realistic dataset size and concurrent usage.
- Real browser rendering nuances across target devices.
- Full restore drill timing and operator procedure on staging infrastructure.

## 5) Stage boundary reminder
After Acceptance Evidence v1, only:
1. blocker bugfixes found in real staging/UAT,
2. optional polish by explicit customer request.

No new major phases are introduced by this registry.
