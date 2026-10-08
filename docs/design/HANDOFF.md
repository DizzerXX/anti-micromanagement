# Design handoff

Visual reference: `screenshots/*.png`, and `mockups/*.html` for exact copy and
spacing (open in a browser; fonts load from Google Fonts). Values:
`tokens.css`. Behaviour rules: `../SPEC.md`.
Mockups are drawings, not code to copy. Build with Filament components and
match structure, hierarchy, copy and states.

| Mockup | Screen |
| --- | --- |
| `Today.html` | Today (home) |
| `LogUpdate.html` | Log update slide-over |
| `Project.html` | Project page, Tasks tab |
| `Reports.html` | Reports › New report with preview |
| `ReportPdf.html` | Exported PDF, A4 |
| `States.html` | Empty, generating, partial-failure states |
| `Sitemap.html`, `TaskStatus.html`, `Flows.html` | Structure, state machine, flows |
| `Tokens.html` | Token sheet |

## 1. Global shell

- Filament panel with sidebar navigation: Today, Projects, Reports; group
  "Pinned projects" listing pinned projects with open-task count (mono); Settings
  at the bottom. Active item: sunk background + 3px ink bar + semibold.
- Top bar: Filament global search ("Search tasks and projects", `/` hint) and the
  user menu. No other buttons in the top bar.
- Page header: optional mono eyebrow, H1 (32/40, 600), one filled primary action
  on the right.
- Content max width 1200px, page padding 32px.
- Keyboard: `L` opens Log update everywhere (ignore when focus is in an input);
  Ctrl+Enter submits the open slide-over; Esc closes it.

## 2. Today

Order and content (see `Today.html`):

1. Eyebrow `THU 8 OCT 2026 · WEEK 41` (mono, uppercase), H1 "Today", primary
   button "Log update" with `L` hint.
2. "This week, 5–11 Oct, compared with last week": four stat tiles —
   Completed, Started, Blocked, Overdue. Value in mono 32px; delta line with
   ▲/▼ and words ("2 more than last week"). Colour rules: SPEC §5.10.
   Filament `StatsOverviewWidget` is acceptable if it can show icon + label.
3. Left column (2/3):
   - **Needs an update** — stale tasks (SPEC §5.7 against today), max 3 rows,
     oldest first. Row: title, "Project › Module" (13px muted), status badge,
     "5 days ago" (mono, blocked colour), "Log" button opening the slide-over
     pre-filled. Hide the card when empty.
   - **Logged today** — today's events, newest first: time (mono), title, path,
     from-badge → to-badge (or "Note" for progress notes), note text.
     Empty: "Nothing logged yet today. Press L to log an update."
4. Right column (1/3):
   - **Blocked** — tasks blocked now: title, days (mono), path, note in a
     blocked-fill box.
   - **Due and overdue** — at most 4 rows: overdue first, most late first
     ("2d overdue", danger), then the nearest due dates (weekday + date). If
     more exist, a "Show all" link to a filtered task list.
   - **Last report** — period, generated time, PDF/XLSX download buttons, link
     "Generate this week's report".

## 3. Log update slide-over

Filament action with `slideOver()`, width ~480px. See `LogUpdate.html`.

| Field | Control | Rules |
| --- | --- | --- |
| Task | searchable select | recent tasks first; shows "Project › Module" under the title; pre-filled and focused-past when opened from a row |
| Status | 2×2 radio tiles with icon + label | defaults to current status; legend shows "Currently <status>" |
| Note | textarea, 4 rows, counter "64 / 500" always visible | label suffix "Required when Blocked" appears for Blocked and same-status; helper "Appears under Blocked in the report. Say what you are waiting on." |
| Logged for | date input (mono) | default today; max today; helper "Backdate it if you forgot to log on the day. Reports use this date." |

- Same status as current = progress note; note becomes required.
- Disallowed transitions are not hidden; selecting one shows the field error
  "Can't go from To do to Blocked. Start it first." (pattern: what + how).
- Footer: "Cancel" (plain, left), "Save and log another" (outlined, keeps panel
  open, clears note, keeps date), "Save" (filled, `Ctrl ↵` hint).
- On success: notification "Logged. <task> is now <status>." with Undo
  (SPEC §4.4). Notification stays 6 seconds.

## 4. Project page

See `Project.html`.

- Breadcrumb "Projects / <name>". H1 project name; meta line "4 modules · 15
  tasks · last update today at 10:42". Actions: "Edit project" (outlined), "New
  task" (filled).
- **Where it stands** card: one stacked bar (done, in progress, blocked, to do,
  2px gaps, 12px tall) with direct labels underneath (icon + name + mono count)
  and "6 of 15 done · ▲ 3 this week". The bar has an `aria-label` with all
  counts.
- Tabs: Tasks (count), Modules (count), Activity.
- Tasks table: grouped by module (collapsible groups; fully done groups start
  collapsed, header "3 of 3 done · collapsed"), tasks with no module grouped
  under "No module" at the end. Columns: Task, Status badge, Last update (mono;
  stale ones in blocked colour with clock icon), Due (mono; overdue in danger
  colour "Wed 7 Oct · 1d late"), actions (Log button + overflow menu: Edit, Move
  to module, Delete). Blocked rows show the blocker note under the title.
- Filters: text, Status, Module; toggle "Group by module".
- Activity tab: the project's events, newest first, same row format as "Logged
  today".

## 5. Reports › New report

See `Reports.html`. Two columns: options form (max 360px) and preview.

- **Period**: segmented Daily / Weekly (default Weekly).
- **Week** (or **Day**): previous / next buttons around the range in mono;
  next is disabled at the current period. Line below: "Compared with <previous
  range>".
- **Scope**: radio All projects / One project (shows a select when chosen).
- **Sections**: checkboxes Completed, Started, Blocked and reopened, Overdue and
  stale, By project (all on).
- **Files**: checkboxes PDF, Spreadsheet (.xlsx) (both on).
- Partial-period note: "This week isn't over. The report covers Mon 5 – Thu 8
  Oct and says so on every file."
- "Generate report" full-width filled button; disabled when the period has no
  events or no file type is selected.
- **Preview** updates live (debounced) from `ReportData`: eyebrow, title with
  "(to Thu 8 Oct)" when partial, summary sentence (16/26), 4-cell headline
  strip, then sections in this order: Completed, Blocked + Reopened (two
  columns), Overdue + Stale (two columns), By project table (numbers
  right-aligned, mono, total row).
- History tab: table of reports (period, scope, generated at, status badge,
  PDF/XLSX links, Retry for partial).

## 6. PDF

See `ReportPdf.html`. A4 portrait, 56px top / 72px side margins. Header with a
2px ink rule: eyebrow, title, right-aligned mono meta (covers, compared with,
generated at). Then summary, headline table, sections as in the preview, footer
"Ledger · <filename>" and "Page n of N". Grayscale-safe: no colour-only meaning;
status icons drawn in ink. Body 13–16px, nothing under 12px.

## 7. States and copy

| Where | State | Copy and action |
| --- | --- | --- |
| Today, first run | no projects | H "Start with a project". Body "Projects hold modules and tasks. Your first report appears once you have logged a few updates." Button "Create project". |
| Report preview | no events in period | H "Nothing logged <range>". Body "Reports are built from the updates you log. Log one first, or open a week that has activity." Buttons "Log update" + link "Go to <last active range> (<n> updates)". |
| Generating | 1–10 s | H "Generating report". Determinate bar + steps: "Read <n> updates, <range>", "Compared with <range>", "Building PDF", "Building spreadsheet". "Cancel" button. |
| Partial failure | one file failed | Red label "PDF failed". H "Spreadsheet saved. PDF didn't build." Body names the cause and the fix. Buttons "Retry PDF" (filled), "Download spreadsheet". Collapsible "Technical details" with the raw error. |
| Tables | no rows | One line + next action, e.g. "No tasks in this module yet. New task". |
| Undo refused | | "Can't undo: newer updates exist or too much time passed." |

## 8. Accessibility checklist

- Text contrast ≥ 4.5:1, control borders ≥ 3:1 (token values already pass;
  see `Tokens.html`).
- Status never by colour alone (icon + label).
- Real buttons and links; icon-only buttons have `aria-label`.
- Form errors linked with `aria-describedby`; focus moves to the first error.
- The stacked bar and stat deltas have text equivalents.
- Visible focus ring on every interactive element (Filament default is fine).

## 9. Sample data (for `SampleDataSeeder`)

"D" = Thu 8 Oct 2026; shift all dates so D = today. Times only matter for
today's events. Format: status history as `status@date`.

**Report Generator** (pinned) — modules Data model, Report engine, Export, Deploy

| Module | Task | History | Due |
| --- | --- | --- | --- |
| Data model | task_events table | in_progress@24 Sep, done@30 Sep | |
| Data model | Status observer writes event | in_progress@30 Sep, done@2 Oct | |
| Data model | Seed realistic sample data | done@25 Sep | |
| Report engine | Weekly diff query | in_progress@25 Sep, done@D 10:42 "Handles the Monday–Sunday boundary and an unfinished current week." | |
| Report engine | Period window helper | in_progress@25 Sep, done@6 Oct | 1 Oct |
| Report engine | Stale detection rule | in_progress@7 Oct | 9 Oct |
| Report engine | Report engine tests | in_progress@1 Oct, blocked@2 Oct "Waiting on the period helper", in_progress@6 Oct | 7 Oct |
| Report engine | Reopen detection | todo | 11 Oct |
| Export | dompdf report template | in_progress@D 09:15 | |
| Export | XLSX export class | in_progress@30 Sep, note@3 Oct "Sheets done, dates still strings" | 11 Oct |
| Export | PDF download route | done@6 Oct | |
| Export | Report history page | todo | |
| Deploy | Docker image | in_progress@25 Sep, blocked@5 Oct "Waiting on base image decision: PHP-FPM + Nginx vs single FrankenPHP container." | |
| Deploy | Compose file | todo | |
| Deploy | Back up the SQLite file | todo | |

**Home Lab** (pinned) — modules Networking, Storage, Power

| Module | Task | History | Due |
| --- | --- | --- | --- |
| Networking | Reverse proxy for Nextcloud | in_progress@28 Sep, note@4 Oct "Certs issued, routing next" | |
| Networking | VLAN for IoT devices | in_progress@24 Sep, done@6 Oct | |
| Networking | Wireguard config | in_progress@6 Oct | |
| Networking | Router firmware update | todo | 6 Oct |
| Networking | Pi-hole upgrade | todo | |
| Storage | Backup rotation script | in_progress@26 Sep, done@D 08:50 | |
| Storage | Nightly backup check | in_progress@29 Sep, done@2 Oct, in_progress@7 Oct | |
| Power | UPS shutdown hook | in_progress@26 Sep, done@7 Oct | |

**Client portal** (pinned) — modules Auth, Billing

| Module | Task | History | Due |
| --- | --- | --- | --- |
| Auth | Project setup | done@29 Sep | |
| Auth | Auth scaffolding | done@1 Oct | |
| Auth | Login page | in_progress@25 Sep, done@5 Oct | |
| Auth | Password reset flow | todo | |
| Billing | Invoice PDF layout | in_progress@5 Oct | 9 Oct |
| Billing | Payment history table | todo | |

Every task also has its creation event (`null → todo`) on or before its first
listed date. Expected weekly report for 5–11 Oct on D: completed 7 (▲2 vs 5),
started 4 (▼1 vs 5), blocked 1 (= 1), overdue 2 (▲1 vs 1), stale 3, reopened 1.
By project: Report Generator 3 / 2 / 1 / 9 / +1, Home Lab 3 / 1 / 0 / 5 / +2,
Client portal 1 / 1 / 0 / 3 / −1.
