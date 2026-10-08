---
paths:
  - "database/**"
  - "app/Models/**"
  - "app/Enums/**"
  - "app/Services/**"
  - "app/Domain/**"
  - "tests/**"
---

# Data layer rules

Full definitions are in `docs/SPEC.md` §3–§5. These are the invariants to check
every time you touch models, migrations, services or tests.

- `task_events` has no `updated_at` and no soft deletes. Model it with
  `const UPDATED_AT = null;`. No code path updates an event row.
- The only delete of an event is `TaskStatusService::undo()`: latest event of
  that task, created under 60 seconds ago. After deleting, recompute
  `tasks.current_status` by replay, in the same transaction.
- Every task gets an initial event (`from_status = null`, `to_status = todo`)
  when created.
- Allowed transitions (anything else throws a domain exception):
  - todo → in_progress, todo → done
  - in_progress → blocked, in_progress → done
  - blocked → in_progress, blocked → done
  - done → in_progress
  - any status → same status (progress note; note required)
- A note is required for → blocked and for progress notes. Max 500 chars.
- `logged_for` cannot be in the future. It can be earlier than today (backdating).
- Replay order for a task: `logged_for` ascending, then `created_at`, then `id`.
- Write a test asserting `current_status` equals replayed status after every
  service operation (transition, progress note, undo, backdated insert).
- Report tests build real event histories with factories and assert exact
  counts for each section in SPEC §5. Cover: period boundaries (Sunday 23:59 vs
  Monday 00:00), partial current week, backdated events, reopen within the
  same period, and empty periods.
