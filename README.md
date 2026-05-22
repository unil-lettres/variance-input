# Variance

Variance is the editorial admin and processing stack used to prepare legacy
Variance publication assets. It lets editors upload textual versions, attach
pagination metadata from `_lignes`, manage facsimiles, run Medite comparisons,
publish comparison outputs to the legacy tree, and export legacy-ready bundles.

Main components:
- **Laravel**: admin UI, API, jobs, health, publication workflow
- **Medite**: Flask + Celery alignment engine
- **Legacy Variance**: public PHP viewer consuming mirrored assets
- **variance-proxy**: single local entry point for admin + legacy site

Useful documentation:
- `descr/architecture.md`
- `descr/workflow.md`
- `descr/facsimiles.md`
- `descr/queues_jobs.md`
- `descr/api_endpoints.md`
- `descr/deployment_notes.md`
- `descr/laravel_current_code_map.md`

## Quick Start

```bash
git clone https://github.com/unil-lettres/variance-input.git
cd variance-input
docker compose up -d --build
```

Main entry points:
- Admin UI: `http://localhost:8080/admin`
- Legacy public site: `http://localhost:8080/`
- Medite debug endpoint: `http://localhost:5000/`

First run:

```bash
docker compose exec laravel php artisan migrate
docker compose exec laravel php artisan db:seed   # optional
```

## Environment Notes

- Copy `laravel/example.env` to `laravel/.env` and adjust local settings as needed.
- `ADMIN_BASE_PATH` controls the mounted admin prefix (`admin` by default).
- `MEDITE_STATUS_URL` controls the Medite task link shown in the admin UI.
- `DB_BACKUP_TIME` and `DB_BACKUP_RETENTION_DAYS` control the scheduled DB dump.

## Daily Editorial Workflow

1. Upload a `.txt` version from the **Versions** card.
2. Upload the `_lignes` file to build the pagination sidecar.
3. Upload or curate facsimiles and manifests.
4. Launch Medite from the **Comparaisons** card.
5. Inject pagination markers into comparison XHTML when needed.
6. Optionally publish to `prod` or `dev`.
7. Export the legacy bundle if a comparison package is needed.

Key storage locations:
- TXT: `storage/app/public/uploads/versions/{folder}.txt`
- TEI: `storage/app/public/uploads/versions/{folder}.xml`
- `_lignes`: `storage/app/private/lignes/{version_id}.txt`
- sidecar: `storage/app/private/pagination/{version_id}.json`
- draft comparisons: `storage/app/public/uploads/{author}/{work}/comparisons/{comparison_id}`
- published comparisons: `storage/app/public/uploads/{author}/{work}/{comparison_folder}`
- facsimiles: `storage/app/public/uploads/{author}/{work}/{version}`
- legacy mirror: `variance/uploads/...`

## Useful Commands

```bash
# Start / stop
docker compose up -d
docker compose down

# Laravel shell
docker compose exec laravel bash

# Migrations
docker compose exec laravel php artisan migrate

# Queue worker (manual)
docker compose exec laravel php artisan queue:work --queue=facsimiles,page-markers,exports --stop-when-empty

# Scheduler tick (manual)
docker compose exec laravel php artisan schedule:run

# DB backup (manual)
docker compose exec laravel php artisan backup:database

# Logs
docker compose logs -f laravel laravel-queue laravel-scheduler medite
```

## Queues & Scheduler

- `page-markers`: pagination sidecars, pagination injection, worker probe
- `facsimiles`: facsimile processing
- `exports`: legacy export zip generation

`laravel-queue` runs `laravel/scripts/run-queue-workers.sh`, which starts
multiple concurrent workers and writes a heartbeat to
`storage/app/private/queue_workers.json`.

`laravel-scheduler` runs `php artisan schedule:run` every minute. It currently
drives:
- `health:scheduler-heartbeat`
- `backup:database`

## Repository Layout

```text
├── docker-compose.yml
├── descr/
├── laravel/
├── medite/
├── nginx/
├── variance/
└── variance_data/   # local runtime data, not for the public repo
```

## Notes

- Admin routes are currently split between `laravel/routes/web.php` and
  `laravel/routes/api.php`.
- Environment-specific deployment coordinates and recovery details belong in
  local internal operations notes, not in the tracked repository.
