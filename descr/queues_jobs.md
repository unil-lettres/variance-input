# Queue Jobs & Background Workers

This document lists the main Laravel jobs and how they are triggered.

---

## Queues

- `page-markers` – pagination sidecar generation & injection.
- `facsimiles` – image batch processing.

The `laravel-queue` container runs:
```
php artisan queue:work --queue=facsimiles,page-markers
```

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

## Operational Reminders

- **Keep the worker alive** – without `laravel-queue`, uploads and pagination requests stall.
- **Monitor logs** –
  ```bash
  docker compose logs -f laravel-queue
  ```
- **Manual run** –
  ```bash
  docker compose exec laravel php artisan queue:work --queue=page-markers --stop-when-empty
  ```
- **Failed jobs** – Use `php artisan queue:failed` / `queue:retry` to inspect and re-run.
