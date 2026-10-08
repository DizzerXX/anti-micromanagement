# Ledger — product spec

Status: v1, agreed design. Owner: the only user of the app.
If something here is ambiguous, ask before coding. Update this file when a
decision changes.

## 1. Purpose

Answer "what moved since last time?" without writing it by hand. The user logs
short status updates on tasks; the app builds a daily or weekly report from that
history, compares it with the previous period, and exports PDF and XLSX.

The thing most likely to kill the app is logging friction, not code. Logging an
update must take one keyboard shortcut, one choice and an optional note.

### In scope (v1)

- Single-user login (Filament auth).
- Projects → optional modules → tasks.
- Logging status changes and progress notes, including backdating.
- Today page, project page, report builder with live preview, report history.
- PDF and XLSX export of a report, saved to history.
- Light and dark theme.

### Out of scope (v1)

- Multiple users, sharing, permissions.
- Scheduled or emailed reports (the app only runs while the container runs).
- AI-written summaries (the summary sentence is rule-based, §5.8).
- User-defined statuses.
- Mobile layout beyond "doesn't break" (desktop-first, pointer input).

## 2. Users and auth

- One user, created by a seeder from `.env` (`LEDGER_USER_EMAIL`,
  `LEDGER_USER_PASSWORD`). No registration page.
- Filament login screen at `/login`; all pages require auth.

## 3. Data model

All timestamps stored in UTC; all reporting dates are local dates in
`Asia/Kuala_Lumpur` (config `app.timezone`).

### projects

| column | type | notes |
| --- | --- | --- |
| id | pk | |
| name | string(120) | required, unique among non-archived |
| description | text null | |
| is_pinned | bool | default false; pinned projects show in the sidebar |
| archived_at | timestamp null | archived projects are hidden and excluded from reports |
| timestamps | | |

### modules

| column | type | notes |
| --- | --- | --- |
| id | pk | |
| project_id | fk → projects, cascade | |
| name | string(120) | required, unique within project |
| position | unsigned int | ordering on the project page |
| timestamps | | |

### tasks

| column | type | notes |
| --- | --- | --- |
| id | pk | |
| project_id | fk → projects, cascade | always set |
| module_id | fk → modules null, null on delete | optional; must belong to `project_id` |
| title | string(200) | required |
| due_date | date null | |
| current_status | enum `todo,in_progress,blocked,done` | cache, see §4.5 |
| last_logged_for | date | cache of the latest event's `logged_for`, for stale checks |
| timestamps | | |

### task_events (append-only)

| column | type | notes |
| --- | --- | --- |
| id | pk | |
| task_id | fk → tasks, cascade | |
| from_status | enum null | null only for the creation event |
| to_status | enum | |
| note | string(500) null | required for → blocked and for progress notes |
| logged_for | date | local date the update is about; ≤ today |
| created_at | timestamp | no `updated_at` |

Indexes: `(task_id, logged_for, created_at, id)`, `(logged_for)`.

### reports

| column | type | notes |
| --- | --- | --- |
| id | pk | |
| period_type | enum `daily,weekly` | |
| start_date / end_date | date | the nominal period |
| effective_end_date | date | `min(end_date, today)` at generation time |
| project_id | fk null | null = all projects |
| sections | json | which sections were included |
| pdf_path / xlsx_path | string null | relative to `storage/app/reports` |
| status | enum `ready,partial,failed` | partial = one file failed |
| error | text null | short technical message |
| created_at | timestamp | |

### settings

Two values, stored in a `settings` table (key/value) and cached:
`week_start` (default `monday`) and `stale_days` (default `3`).

## 4. Domain rules

### 4.1 Statuses

`todo` (To do), `in_progress` (In progress), `blocked` (Blocked), `done` (Done).
Implement as a backed PHP enum with label, icon and colour-name methods.

### 4.2 Transitions

| from → to | allowed | note |
| --- | --- | --- |
| todo → in_progress | yes | optional |
| todo → done | yes | optional |
| in_progress → blocked | yes | **required** |
| in_progress → done | yes | optional |
| blocked → in_progress | yes | optional |
| blocked → done | yes | optional |
| done → in_progress | yes (reopen) | optional |
| X → X (same status) | yes (progress note) | **required** |
| anything else | no | domain exception, friendly form error |

Creating a task writes an event `null → todo` with `logged_for = today`.

### 4.3 Backdating

`logged_for` defaults to today and may be any earlier date. It may not be in
the future. The form shows a date input labelled "Logged for".

### 4.4 Undo

After a successful log, a notification offers Undo. Undo deletes the event only
if it is still the task's latest event and was created less than 60 seconds
ago; otherwise it shows "Can't undo: newer updates exist or too much time
passed." Undo then recomputes the task caches by replay. This is the only
event delete in the system.

### 4.5 Caches and replay

- Replay order: `logged_for` asc, `created_at` asc, `id` asc.
- `status_at(task, date)` = `to_status` of the last event with
  `logged_for ≤ date`, or "did not exist" if none.
- `tasks.current_status` = `status_at(task, today)`; `tasks.last_logged_for` =
  max `logged_for`. Both are updated in the same transaction as the event by
  `TaskStatusService`, the only writer.

## 5. Report definitions

All metrics are computed by `ReportBuilder` from events and returned as a
`ReportData` DTO. Archived projects are excluded. Scope is all projects or one
project.

### 5.1 Period

- Weekly: from the configured week start through the 7th day.
- Daily: one date.
- `effective_end = min(end, today)`. If `effective_end < end`, the report is
  partial and every output says "Covers <start> – <effective_end> (week in
  progress)".
- Previous period: same length, immediately before `start`. Compare against its
  full length (it is in the past).

Let `S = start`, `E = effective_end`.

### 5.2 Completed

Tasks with at least one event `→ done` whose `logged_for` is in [S, E], **and**
`status_at(task, E) = done`. Date shown = `logged_for` of the last `→ done`
event in the period. A task reopened later in the same period is not completed.

### 5.3 Started

Tasks whose first event leaving `todo` (to `in_progress`) has `logged_for` in
[S, E]. A task that went straight `todo → done` counts as completed, not
started.

### 5.4 Blocked

Tasks with `status_at(task, E) = blocked`. Show the note of the latest `→
blocked` event and days blocked = `E − logged_for` of that event.

### 5.5 Reopened

Tasks with an event `done → in_progress` whose `logged_for` is in [S, E]. Show
"Done <date of previous done> → In progress, <date>".

### 5.6 Overdue

Tasks with `due_date < E` (strictly) and `status_at(task, E) ≠ done`. Show days
late = `E − due_date`.

### 5.7 Stale ("No update for N+ days")

Tasks with `status_at(task, E) = in_progress` and
`E − (latest logged_for ≤ E) ≥ stale_days`. Progress notes count as updates.

### 5.8 Summary sentence

Rule-based, built from the numbers, parts omitted when zero. Template:

"{completed} tasks completed{, N more|fewer than last week}. {started} started.
{blocked} blocked{: <title>, for <n> days — only when exactly one}. {overdue}
are overdue and {stale} have had no update for {stale_days} days or more."

Use "1 task" vs "N tasks" correctly. "last week" / "yesterday" depends on period
type.

### 5.9 By project

One row per project in scope: completed, started, blocked at E, open at E
(status ≠ done), and completed delta vs previous period. Plus a total row.

### 5.10 Headline deltas

Completed, started, blocked (at end of each period) and overdue (at end of each
period) are compared with the previous period. Show ▲/▼/= with the signed
number. Colour: more completed = success colour; more overdue = danger colour;
everything else muted.

## 6. Exports and history

- Generating writes one `reports` row and the files under
  `storage/app/reports/{yyyy}/`. Filenames: `weekly-2026-W41.pdf`,
  `daily-2026-10-08.xlsx` (ISO week for weekly).
- PDF: A4 portrait, dompdf, one Blade view rendering `ReportData`. Must read in
  grayscale (status shown by icon + text). Font: IBM Plex Sans/Mono embedded.
- XLSX sheets: `Summary` (headline numbers + summary sentence), `Completed`,
  `Started`, `Blocked`, `Reopened`, `Overdue`, `Stale`, `By project`. Dates as
  real date cells, not strings.
- If one file fails, keep the other, set `status = partial`, store the error,
  and show the partial-failure state (HANDOFF §7) with Retry for the failed file.
- History lists reports newest first with download links. Generating the same
  period again creates a new row; older rows are kept.

## 7. Edge cases to test

- Event logged for Sunday counts in that week, not the next.
- Backdated event inserted between existing events changes replay correctly.
- Task created mid-period with no other events: appears in nothing except, if
  overdue, Overdue.
- Task completed then reopened in the same period: in Reopened, not Completed.
- Module deleted: its tasks keep `project_id`, `module_id` becomes null.
- Project archived: excluded from new reports; old report files unchanged.
- Empty period: preview shows the empty state; Generate is disabled.
- Undo after a newer event exists: refused.

## 8. Later (not v1)

- Optional AI summary written from `ReportData` only, never from raw rows.
- Scheduled generation with catch-up on container start.
- User-defined statuses.

## 9. Open questions

None blocking. Record new ones here instead of guessing.
