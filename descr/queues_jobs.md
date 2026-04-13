# Queue Jobs & Background Workers

This document lists the main Laravel jobs and how they are triggered.

---

## Queues

- `page-markers` – pagination sidecar generation & injection.
- `facsimiles` – image batch processing.
- `exports` – legacy zip preparation for comparison downloads.

The `laravel-queue` container runs:
```
php artisan queue:work --queue=facsimiles,page-markers,exports
```

Before spawning workers, `laravel/scripts/run-queue-workers.sh` now waits for
Laravel to reach the MariaDB-backed queue/cache tables. This avoids the common
boot race where the queue container starts before MariaDB is ready and the
first `queue:work` processes exit immediately with `Connection refused`.

The `laravel-scheduler` container runs:
```
php artisan schedule:run
```
once per minute in a loop. This is required for scheduler-driven health checks and any future scheduled commands.

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

## Operational Reminders

- **Keep the worker alive** – without `laravel-queue`, uploads and pagination requests stall.
- **Keep the scheduler alive** – without `laravel-scheduler`, `/health` will report a missing scheduler heartbeat.
- **Monitor logs** –
  ```bash
  docker compose logs -f laravel-queue laravel-scheduler
  ```
- **Manual run** –
  ```bash
  docker compose exec laravel php artisan queue:work --queue=page-markers,exports --stop-when-empty
  ```
- **Manual scheduler tick** –
  ```bash
  docker compose exec laravel php artisan schedule:run
  ```
- **Failed jobs** – Use `php artisan queue:failed` / `queue:retry` to inspect and re-run.
