# Ledger

A personal daily and weekly report generator. Log short status updates on
tasks; Ledger turns that history into a report compared with the previous
period, on screen and as PDF and XLSX.

Single user, runs on localhost. Laravel 13 + Filament 5, SQLite.

## Run it with Docker

```bash
cp .env.example .env          # set LEDGER_USER_EMAIL / LEDGER_USER_PASSWORD
docker compose up --build
```

Open <http://localhost:8080> and sign in with the email and password from
`.env` (defaults: `me@ledger.test` / `password`). On every start the container
runs migrations and makes sure that user exists. The app key is generated on
first start and kept on the data volume.

To start with the sample projects from the design mockups, set
`LEDGER_SEED_SAMPLE_DATA=true` in `.env` before the first start.

### Data and backups

Everything lives on the `ledger-data` volume, mounted at `/data`:

| Path | What |
| --- | --- |
| `/data/database.sqlite` | all projects, tasks, updates, settings, report history |
| `/data/reports/{yyyy}/` | generated PDF and XLSX files |
| `/data/app_key` | the app key (sessions) |

Back up by copying the SQLite file and the reports folder. With the container
running:

```bash
docker compose exec ledger sqlite3 /data/database.sqlite ".backup /data/backup.sqlite"
docker compose cp ledger:/data/backup.sqlite ./ledger-backup.sqlite
docker compose cp ledger:/data/reports ./ledger-reports
```

Restore by copying them back to the same paths and restarting. `docker compose
down` keeps the volume; `docker compose down -v` deletes it.

## Run it locally

Needs PHP 8.3+ (with `intl`, `zip`, `gd`, `pdo_sqlite`), Composer and Node 20.

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed   # user from .env + sample data
php artisan serve --port=8080
```

## Using it

- **L** opens *Log update* anywhere; **/** focuses search; **Ctrl+Enter** saves
  the open panel; **Esc** closes it.
- **Today** shows this week against last week, tasks that need an update, what
  you logged today, blocked and due tasks, and the last report.
- **Projects** hold optional modules and tasks. Row **Log** opens the panel with
  the task filled in.
- **Reports › New report** previews the report live; **Generate** writes PDF and
  XLSX to History. If one file fails the other is kept and you can retry.
- **Settings**: the day weeks start on, and after how many days an in-progress
  task counts as stale.

## Development

```bash
php artisan test        # 100+ tests against a real SQLite database
./vendor/bin/pint       # format
```

Product rules: `docs/SPEC.md`. Build plan: `docs/PLAN.md`. Design:
`docs/design/HANDOFF.md` and the mockups in `docs/design/`. Notes for coding
agents: `CLAUDE.md`.

IBM Plex fonts are under the SIL Open Font License (`resources/fonts/OFL.txt`).
