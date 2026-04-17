# Variance — Agent Guide

This file summarizes how the Variance stack is organized, where data lives, and how to operate the system. Source material comes from `descr/`.

## Architecture (containers)
- **variance-proxy (nginx)**: front door (dev: `localhost:8080`). Routes `/admin` and `/health` to Laravel, `/` to legacy PHP.
- **laravel**: admin UI + API (versions, pagination, facsimiles, comparisons, publish).
- **laravel-queue**: background jobs (`facsimiles`, `page-markers`, `exports`).
- **laravel-scheduler**: scheduled commands / health heartbeat (`schedule:run` loop).
- **medite**: Flask + Celery diff engine (`/run_diff2`, `/task_status/{id}`).
- **variance-web**: legacy PHP public site (read‑only, consumes uploaded assets).
- **mariadb / redis**: persistence and queues.

See `descr/architecture.md` for full topology.

## Primary entry points
- Admin UI: `http://localhost:8080/admin` (default; configurable via `ADMIN_BASE_PATH`)
- Legacy public: `http://localhost:8080/` (prod) and `/dev` (draft)
- Medite debugging: `http://localhost:5000/`

## Key paths & data flow
- **TXT upload**: `storage/app/public/uploads/versions/{folder}.txt`
- **TEI**: `storage/app/public/uploads/versions/{folder}.xml`
- **_lignes**: `storage/app/private/lignes/{version_id}.txt`
- **Sidecar**: `storage/app/private/pagination/{version_id}.json`
- **Comparison XHTML**: `storage/app/public/uploads/{author}/{work}/comparisons/{comparison_id}`
- **Published comparison (prod)**: `storage/app/public/uploads/{author}/{work}/{comparison_folder}`
- **Published comparison (dev)**: `storage/app/public/uploads/{author}/{work}/comparisons/{comparison_id}`
- **Legacy mirror**: `variance/uploads/...` (public PHP reads here)
- **Facsimiles**: `storage/app/public/uploads/{author}/{work}/{version}`
- **Manifests**: `images_{role}_{author--work--comparison}.json` inside the facsimile folder
- **Work cover images**: `public/uploads_images/{hash}.{ext}` (mirrors to `variance/uploads_images/`)
- **Work PDFs**: `public/uploads/pdf/{work_id}.pdf` (mirrors to `variance/uploads/pdf/`)

Reference: `descr/workflow.md`, `descr/facsimiles.md`.

## Queues & jobs
- `page-markers`: `ApplyLignesJob`, `InjectComparisonPaginationJob`, `HealthcheckProbeJob`
- `facsimiles`: `ProcessFacsimileImage`
- `exports`: `GenerateLegacyExportJob`

Worker container runs `laravel/scripts/run-queue-workers.sh` which spawns
multiple `queue:work` processes for `facsimiles,page-markers,exports`. A heartbeat
is written to `storage/app/private/queue_workers.json`.
Scheduler container runs `php artisan schedule:run` every minute and writes
`storage/app/private/scheduler_heartbeat.json` through `health:scheduler-heartbeat`.
It also runs the daily `backup:database` command.
See `descr/queues_jobs.md`.

## Publication model (prod/dev)
- Comparisons are **unpublished by default**.
- Admin publishes with a scope (`prod` or `dev`).
- Publishing:
  - `prod`: copies comparison outputs to `storage/app/public/uploads/{author}/{work}/{comparison_folder}` and mirrors to legacy.
  - `dev`: keeps outputs in `storage/app/public/uploads/{author}/{work}/comparisons/{id}` and mirrors to legacy.
  - Ensures facsimiles + manifests exist.
  - Optional default marker insertion when requested via publish API.

## Medite parameters (admin)
- Input parameters: **Pivot**, **Ratio**, **Seuil**, **Sensibilité casse**, **Séparateurs**.
- Output metrics: **Durée**, **Pic mémoire**, plus counts for `d/i/r/s.xhtml`.

## Admin API (partial)
See `descr/api_endpoints.md`. Notables:
- `/comparisons/by-work?work_id={id}&light=1`
- `/comparisons/{id}/details`
- `/api/comparisons/publication-counts`
- `/api/run_medite`
- `/api/comparisons/{id}/page-markers`
- `/api/comparisons/{id}/pagination/from-xhtml`
- `/api/versions/{id}/pagination/from-pb`
- `/api/versions/{id}/pagination/merge-from-pb`
- `/api/versions/{id}/reader/rebuild`
- `/comparisons/{id}/comments`
- `/comparisons/{id}/export/status`
- `/chapters/import/preview`
- `/chapters/import/commit`
- `/api/publish_xhtml`

## Access control (current)
- Users are `is_admin` or researcher (non-admin).
- Non-admins only manage their own comparisons (`created_by`); legacy comparisons remain visible but read-only.
- Work/media edits are blocked for legacy data and require Work policy permission.
- Password changes are available at `/account/password`.

## Editors
- Version XML editor: `/version/{version}/editor` (includes facsimile ignore toggles).
- Comparison XML editor: `/comparison/{comparison}/editor` (editable only when unpublished + manifest JSON exists).

## Deploy notes
See `descr/deployment_notes.md` for TLS/proxy, volumes, legacy import, and VM recovery steps.
See `descr/dependency_updates.md` for the regular dependency update process.
Detailed staging and production infrastructure coordinates are documented in
internal operations notes and should not be committed to the public repository.

## Local file inventory for legacy import
- `descr/legacy_texts_lignes.md` lists available legacy TXT/_lignes files in `variance/uploads/`.

## Local dev commands
```
# Boot stack
docker compose up -d

# Laravel shell
docker compose exec laravel bash

# Migrations
docker compose exec laravel php artisan migrate

# Queue worker (if not using laravel-queue container)
docker compose exec laravel php artisan queue:work --queue=facsimiles,page-markers,exports

# Logs
docker compose logs -f laravel laravel-queue medite
```

## Common files
- Docker: `docker-compose.yml`, `nginx/default.conf`, `variance/docker/...`
- Laravel: `laravel/app/Http/Controllers/*`, `laravel/app/Services/PageMarkerService.php`
- Medite: `medite/app/flask_app.py`, `medite/app/variance/scripts/diff.py`
- Dependency updates: `descr/dependency_updates.md`, `.github/dependabot.yml`, `.github/workflows/dependency-checks.yml`
- Current Laravel map: `descr/laravel_current_code_map.md`
