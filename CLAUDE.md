# Ledger — personal daily/weekly report generator

Single-user Laravel + Filament app, run on localhost in one Docker container.
You log status updates on tasks; the app turns that history into daily or
weekly reports (on screen, PDF, XLSX) compared with the previous period.

## Read before working

- `docs/SPEC.md` — product rules, data model, exact report definitions. Source of truth.
- `docs/PLAN.md` — phases, scope per phase, "done when" checks.
- `docs/design/HANDOFF.md` — screens, states, copy, Filament mapping.
- `docs/design/tokens.css` — colour, type, spacing tokens for the Filament theme.
- `docs/design/screenshots/*.png` — what each screen should look like. Read
  the matching PNG before building or changing a screen.
- `docs/design/mockups/*.html` — the same screens as HTML, for exact copy and
  spacing. Visual reference only: never copy this markup into the app.

## Where things live

- Domain: `app/Services/TaskStatusService.php` (only status writer),
  `app/Domain/StatusTimeline.php` (replay), `app/Domain/Reports/*`
  (`ReportBuilder`, `ReportData`, `SummarySentence`, `ReportGenerator`, exporters).
- UI: `app/Providers/Filament/AppPanelProvider.php` (panel, nav, hooks),
  `app/Filament/Pages` (Today, Reports, Settings), `app/Filament/Resources`
  (Projects, Tasks list), `app/Livewire` (LogUpdate slide-over, Topbar,
  project tabs), `resources/views/components/ld/*` (badge, icon, rows).
- Theme: `resources/css/filament/app/theme.css` + `tokens.css` (copy of
  `docs/design/tokens.css`); icons traced from the mockups in `resources/svg/ledger`.
- PDF: `resources/views/reports/pdf.blade.php`, `resources/pdf/report.css`
  (dompdf: no CSS variables or flexbox, line-heights divided by 1.43),
  fonts in `resources/fonts`.

If code and SPEC disagree, stop and ask. Do not silently pick one.

## Stack

Installed (checked with Composer on 2026-10-08):

| Piece | Version |
| --- | --- |
| PHP | 8.4 (local and Docker `php:8.4-fpm-alpine`) |
| Laravel | 13.35 |
| Filament | 5.10.1 (Livewire 4.4) — v5 APIs: `Filament\Schemas\Schema`, `Filament\Actions\*` |
| barryvdh/laravel-dompdf | 3.1 (dompdf 3.1) |
| maatwebsite/excel | 4.0.3 (PhpSpreadsheet 5.10) |
| Tests | PHPUnit 12 (the skeleton's choice) |

- PHP 8.3+, latest stable Laravel, latest stable Filament that supports it.
  Verify versions with Composer before scaffolding; read the docs for the
  installed Filament major version, because v3 and v4 APIs differ.
- SQLite (single file on a Docker volume). No Redis, no queue worker in v1:
  report generation runs synchronously.
- PDF: `barryvdh/laravel-dompdf`. XLSX: `maatwebsite/excel`.
- Tests: Pest (or PHPUnit if the skeleton chose it) against a real SQLite
  database with factories.
- Fonts: IBM Plex Sans + IBM Plex Mono.

Ask before adding any other Composer or npm package.

## Non-negotiables

1. **History is append-only.** Every status change or progress note is a new
   `task_events` row. Never update or delete event rows, with one exception:
   Undo may delete a task's latest event within 60 seconds (SPEC §4.4).
2. **`tasks.current_status` is a cache.** It must always equal the status you
   get by replaying that task's events. Write the event and update the cache in
   one DB transaction, inside `TaskStatusService`. Nothing else writes status.
3. **Reports are derived, never typed.** All report numbers come from
   `ReportBuilder` reading events. No manual counts, no stored totals.
4. **One report data object feeds every output.** The on-screen preview, the
   PDF Blade view and the XLSX export all render the same `ReportData` DTO.
5. **Dates use `logged_for`, not `created_at`,** for reporting. Timezone is
   `Asia/Kuala_Lumpur`. Week starts on the configured day (default Monday).
6. **Colour means status.** UI chrome is ink on paper. Only status, overdue
   and reopened get colour, and always with an icon and a text label.
7. **No mocks or fakes in tests** unless the user asks. Use the real database,
   real services and factories. Freezing the clock with `$this->travelTo()` is
   allowed and expected for date logic.

## Code conventions

- Format with Laravel Pint defaults; strict types (`declare(strict_types=1);`)
  in every PHP file you write.
- Business logic lives in `app/Services` or `app/Domain`, never in Filament
  resources, Livewire components or Blade views.
- PHP enums for statuses (`TaskStatus`), not strings scattered in code.
- Small classes, explicit return types, no static helpers holding state.
- Validate at the boundary (Filament forms) **and** in the domain service.
- No N+1 queries in tables or report building; eager-load or aggregate.
- Escape all user text in Blade (`{{ }}`); never `{!! !!}` on user data.

## Workflow

- Work one phase of `docs/PLAN.md` at a time. Start each phase with a short
  plan, wait for approval, then implement.
- End each phase by running the tests and the phase's "done when" checks.
  Report what passed, what didn't, and anything you changed in SPEC.
- Commit per logical step with clear messages. Do not push.
- When unsure about behaviour, check SPEC, then the mockups, then ask.

## Commands

```bash
composer install && npm install  # dependencies
npm run build                    # Filament theme + self-hosted IBM Plex (Vite)
php artisan test                 # all tests
php artisan test --filter=Report # report engine tests
./vendor/bin/pint                # format
php artisan migrate:fresh --seed # reset local DB with sample data (user from .env)
php artisan serve --port=8080    # run locally
php artisan ledger:install       # migrate + create the .env user (what Docker runs)
docker compose up --build        # run in Docker on localhost:8080
```

Force a file to fail (to see the partial-failure state): `LEDGER_FORCE_PDF_FAILURE=true`
or `LEDGER_FORCE_XLSX_FAILURE=true` in `.env`.

Update this section when the real commands change.
