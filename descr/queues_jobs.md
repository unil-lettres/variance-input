# Queue Jobs & Background Workers

This document lists the main Laravel jobs, the queues they use, and the scheduler/worker processes that keep them running.

---

## Queues

- `page-markers` – pagination sidecar generation & injection.
- `facsimiles` – image batch processing.
- `exports` – legacy zip preparation for comparison downloads.

The `laravel-queue` container does not run a single worker anymore. It runs
`laravel/scripts/run-queue-workers.sh`, which:
- waits for MariaDB-backed queue/cache dependencies to become ready
- spawns multiple `queue:work` processes (default: `QUEUE_WORKERS=5`)
- processes `facsimiles,page-markers,exports`
- writes a heartbeat to `storage/app/private/queue_workers.json`

The effective artisan command is:
```
php artisan queue:work --queue=facsimiles,page-markers,exports --sleep=2 --timeout=600 --tries=1 --memory=512
```

Before spawning workers, `laravel/scripts/run-queue-workers.sh` waits for
Laravel to reach the MariaDB-backed queue/cache tables. This avoids the common
boot race where the queue container starts before MariaDB is ready and the
first `queue:work` processes exit immediately with `Connection refused`.

The `laravel-scheduler` container runs:
```
php artisan schedule:run
```
once per minute in a loop. This is required for scheduler-driven health checks and any future scheduled commands.
The scheduler currently runs:
- `health:scheduler-heartbeat` every minute
- `backup:database` once per day (default `03:15`, configurable via `DB_BACKUP_TIME`)

---

## Pagination Jobs

### `ApplyLignesJob`
- **Queue**: `page-markers`
- **Triggered by**: `_lignes` upload (`VersionController::uploadLignes`).
- **Task**: Parse the `_lignes` file, match phrases in the TEI version, and produce `storage/app/private/pagination/{version_id}.json` (sidecar).
- **Progress file**: `storage/app/tmp/pager/{version_id}.json`.

### `InjectComparisonPaginationJob`
- **Queue**: `page-markers`
- **Triggered by**: “Injecter la pagination” button (`ComparisonController::applyPageMarkers`).
- **Task**: Load sidecars for source/target versions and inject `<span class="page-marker">` into comparison XHTML files.
- **Progress file**: `storage/app/tmp/pager/comparisons/{comparison_id}.json`.

### `HealthcheckProbeJob`
- **Queue**: `page-markers`
- **Triggered by**: Health probe dispatch inside `HealthController`.
- **Task**: Confirm that workers really execute queued jobs, then update the probe state used by `/admin/health/report`.
- **Artifacts**:
  - cache keys such as `health:probe:*`
  - `storage/app/private/health_probe.txt`

---

## Facsimile Jobs

### `ProcessFacsimileImage`
- **Queue**: `facsimiles`
- **Triggered by**: Facsimile upload (`FacsimileController::store`).
- **Task**: Save the uploaded file, normalise the final name, generate thumbnails, and mirror draft assets into `storage/app/public/uploads/{author}/{work}/{version}`.
 
Publication runs synchronously during comparison publish (`PublishController::publish`) – it copies processed images and ensures manifest JSONs exist for the comparison. No separate job is queued.

---

## Export Jobs

### `GenerateLegacyExportJob`
- **Queue**: `exports`
- **Triggered by**: Legacy export request from the comparisons table (`POST /comparisons/{comparison}/export`).
- **Task**: Build a zip under `storage/app/private/exports/comparisons/{comparison_id}/` containing:
  - the published comparison folder for `prod`, or the draft comparison folder for `dev`
  - the source and target manifest JSON files
  - only the facsimile images referenced by those manifests
- **Status file**: `storage/app/private/exports/comparisons/{comparison_id}.json`

The front-end polls `GET /comparisons/{comparison}/export/status` until the snapshot reaches `ready`, then replaces the export button with a direct download link.

---

## Scheduler-driven Commands

### `health:scheduler-heartbeat`
- **Triggered by**: Laravel scheduler every minute.
- **Task**: Write `storage/app/private/scheduler_heartbeat.json` so the health page can detect whether the scheduler loop is alive.

### `backup:database`
- **Triggered by**: Laravel scheduler once per day.
- **Default time**: `03:15`
- **Config**:
  - `DB_BACKUP_TIME`
  - `DB_BACKUP_RETENTION_DAYS` (default `14`)
- **Task**: Create a compressed MariaDB dump under `storage/app/private/db_backups/` and prune expired backups.

---

## Operational Reminders

- **Keep the worker alive** – without `laravel-queue`, uploads and pagination requests stall.
- **Keep the scheduler alive** – without `laravel-scheduler`, `/health` will report a missing scheduler heartbeat.
- **Watch the worker probe** – if `/admin/health/report` shows the worker probe stuck in `pending`, the queue container may be up but not actually consuming jobs.
- **Monitor logs** –
  ```bash
  docker compose logs -f laravel-queue laravel-scheduler
  ```
- **Manual run** –
  ```bash
  docker compose exec laravel php artisan queue:work --queue=facsimiles,page-markers,exports --stop-when-empty
  ```
- **Manual scheduler tick** –
  ```bash
  docker compose exec laravel php artisan schedule:run
  ```
- **Manual DB backup** –
  ```bash
  docker compose exec laravel php artisan backup:database
  ```
- **Failed jobs** – Use `php artisan queue:failed` / `queue:retry` to inspect and re-run.
