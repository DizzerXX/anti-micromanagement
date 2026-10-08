# Build plan

One phase at a time. Start each phase with a short plan, wait for approval,
build, then run the "done when" checks and report results.

## Phase 0 — Skeleton

- New Laravel app, SQLite, Filament panel at `/`, login only (no registration).
- Check and record installed versions of PHP, Laravel, Filament in CLAUDE.md.
- Fonts (IBM Plex Sans/Mono) and a custom Filament theme that loads
  `docs/design/tokens.css` values. Primary/gray = Stone; register named colours
  `todo`, `progress`, `blocked`, `done` from the tokens.
- User seeder from `.env`.

**Done when:** `php artisan test` passes; you can log in; an empty panel shows
the sidebar with Today, Projects, Reports, Settings; dark mode toggle works.

## Phase 1 — Data model and domain

- Migrations and models per SPEC §3. `TaskStatus` enum per SPEC §4.1.
- `TaskStatusService`: `create`, `transition`, `progressNote`, `undo`, with
  transaction, guards (SPEC §4.2–4.4) and cache updates (§4.5).
- Factories that can build realistic histories (helper to create a task with a
  sequence of `[status, logged_for, note]`).
- `SampleDataSeeder` reproducing the mockup data (3 projects, tasks and events
  in HANDOFF §9), with dates shifted so "Thu 8 Oct 2026" becomes today's
  date. That keeps the Today page meaningful whenever you run it. Tests that
  need the exact mockup dates freeze the clock with `$this->travelTo(...)`.

**Done when:** tests cover every allowed and refused transition, note
requirement, future `logged_for` refused, undo window and "not latest" refusal,
and the cache-equals-replay invariant after each operation.

## Phase 2 — CRUD and logging UI

- Project resource (list, create, edit, archive, pin) and project view page
  with the status strip, Tasks table grouped by module (collapsible), filters,
  row actions Log and More.
- Module management on the project (Modules tab).
- Global **Log update** action as a slide-over (HANDOFF §3), opened by key `L`
  anywhere and from table rows with the task pre-filled. Success notification
  with Undo.

**Done when:** you can create a project, modules and tasks, log every
transition and a progress note from the slide-over, undo within 60 s, and the
project page matches `mockups/Project.html` in structure.

## Phase 3 — Report engine

- `ReportBuilder` → `ReportData` DTO implementing SPEC §5 exactly.
- `SummarySentence` formatter (§5.8).
- No UI yet beyond a temporary debug route or test output.

**Done when:** tests with hand-built histories assert exact section contents
for: a full past week, the current partial week, a single day, backdated
events, reopen in the same week, Sunday/Monday boundary, empty period, one
project vs all projects. Seeded data reproduces the numbers in
`mockups/Reports.html` (7 completed ▲2, 4 started, 1 blocked, 2 overdue,
3 stale).

## Phase 4 — Today page, report builder, exports

- Today page (HANDOFF §2) built from `ReportBuilder` plus task queries.
- Reports › New report: options form + live preview from `ReportData`
  (HANDOFF §5). Generate writes PDF + XLSX (SPEC §6), records history.
- Reports › History table with downloads.
- Settings page: week start, stale days.
- Empty, generating and partial-failure states (HANDOFF §7).

**Done when:** generating the seeded week produces a one-page A4 PDF that
matches `mockups/ReportPdf.html` in content and reads in grayscale; the XLSX
opens with the sheets in SPEC §6 and real date cells; forcing the PDF step to
fail leaves a partial report with a working Retry.

## Phase 5 — Docker

- One image (default: php-fpm + nginx under supervisord, or FrankenPHP if you
  propose it with reasons). SQLite file and `storage/app/reports` on a named
  volume. `docker compose up --build` serves the app on `localhost:8080`.
- Backup note in README: copy the SQLite file and the reports folder.

**Done when:** a fresh clone runs with `cp .env.example .env && docker compose up
--build`, migrations run on start, data survives `docker compose down && up`.
