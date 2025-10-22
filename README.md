# Variance & Medite Integration

Variance is a Docker-based toolchain that lets editors upload textual
versions, generate Medite comparisons, manage facsimiles, and produce
publish-ready assets with pagination markers derived from `_lignes`
files. The project is split into:

- **Laravel** – Admin UI, API, job queue, pagination services.
- **Medite (Flask + Celery)** – Runs the alignment engine and produces
  TEI/XHTML comparison outputs.
- **Variance web (legacy PHP)** – Public-facing viewer that consumes the
  assets prepared by the admin app.
- **variance-proxy (Nginx)** – Unified entry point on
  `http://localhost:8080` to reach Laravel (`/admin`), legacy Variance (`/`),
  and Medite endpoints from a single port.

> Detailed documentation: `descr/architecture.md`, `descr/workflow.md`,
> `descr/facsimiles.md`, `descr/queues_jobs.md`, `descr/api_endpoints.md`,
> `descr/deployment_notes.md`.

---

## Quick Start

1. **Clone & Boot**
   ```bash
   git clone https://github.com/unil-variance/variance2.git
   cd variance2
   docker compose up -d --build
   ```

   This brings up:
   - `laravel` (admin web app on http://localhost:8000)
   - `medite` (Flask API on http://localhost:5000)
   - `laravel-queue` (queue worker for pagination & facsimile jobs)
   - Supporting services (MariaDB, Redis, Nginx proxy, legacy PHP site)

2. **Environment**
   - `laravel/.env` is committed for development; tweak DB or queue
     settings if needed.
   - The queue worker already runs
     `php artisan queue:work --queue=facsimiles,page-markers`, so
     pagination and facsimile jobs are processed automatically.

3. **Initial setup (first run)**
   ```bash
   docker compose exec laravel php artisan migrate
   docker compose exec laravel php artisan db:seed   # optional fixtures
   ```

---

## Daily Workflow

- **Upload version** – Admin UI → Versions → “Téléverser une version”. Creates
  a TEI file under `storage/app/public/uploads/versions/`.

- **Attach pagination (`_lignes`)** – Upload the `_lignes` text. The system
  generates a sidecar JSON (`storage/app/private/pagination/{version}.json`)
  containing character offsets for every facsimile marker.

- **Import facsimiles** – Optional. Upload image batches via the UI; jobs
  resize/organise them under `storage/app/public/uploads/{author}/{work}/{version}`.

- **Run Medite** – Start a comparison run from the comparisons card. Output
  shards land in `storage/app/public/uploads/{author}/{work}/comparisons/{id}`.

- **Inject pagination** – Click “Injecter la pagination” next to a comparison.
  A queue job reads the sidecar(s) and inserts `<span class="page-marker">` tags
  into `source.xhtml` / `target.xhtml`.

- **Publish** – Once satisfied, publish the comparison to the legacy public
  tree (optional).

---

## Useful Commands

| Purpose                        | Command |
|-------------------------------|---------|
| Tail Laravel queue logs       | `docker compose logs -f laravel-queue` |
| Run queue worker manually     | `docker compose exec laravel php artisan queue:work --queue=page-markers --stop-when-empty` |
| Artisan shell                 | `docker compose exec laravel php artisan tinker` |
| Composer install (Laravel)    | `docker compose exec laravel composer install` |
| NPM build (if needed)         | `docker compose exec laravel npm run build` |

---

## Project Structure (abridged)

```
├── docker-compose.yml
├── descr/                    # Developer documentation (not committed)
├── laravel/                  # Admin application (PHP/Laravel)
│   ├── app/
│   ├── routes/
│   └── ...
├── medite/                   # Flask + Celery Medite runner
│   └── app/variance/...
├── variance/                 # Legacy PHP frontend (read-only in dev)
└── variance_data/            # Runtime uploads & generated outputs
```

---

## Full Pipeline

1. **Upload a version (`Versions` card)**
   - Accepts `.txt` files (≤ 8 MB, `text/plain`).
   - `VersionController::store` normalises text (UTF‑8, whitespace collapse),
     inserts `<lb/>`, wraps it in a TEI skeleton, and writes both the raw text
     and TEI XML to `storage/app/public/uploads/versions/{base}.{txt,xml}` (with
     public mirrors under `public/uploads/...`).  
   - A `versions` row is created with the generated folder slug.

2. **Upload `_lignes`**
   - Raw file saved to `storage/app/private/lignes/{version_id}.txt`.
   - `ApplyLignesJob` parses the `_lignes` entries, matches them against the TEI
     text, and stores a pagination sidecar JSON at
     `storage/app/private/pagination/{version_id}.json` containing
     `{ char_index, page, image_code, phrase, context }`.
   - Version-level progress is tracked in `storage/app/tmp/pager/{version_id}.json`
     so the UI shows when the sidecar is ready.

3. **Run Medite**
   - Launch from the comparisons table. `MediteController::runMedite` calls the
     Flask service which executes the Celery task.
   - Medite writes TEI diff + XHTML components under
     `/app/uploads/{author}/{work}/comparisons/{comparison_id}` which Laravel
     mirrors into `storage/app/public/uploads/{author}/{work}/comparisons/{id}`.
   - Comparison metadata is stored/updated in the DB.

4. **Inject pagination markers**
   - Click “Injecter la pagination”. `ComparisonController::applyPageMarkers`
     ensures both versions have sidecars, marks the comparison queued, and
     dispatches `InjectComparisonPaginationJob`.
   - The job loads `storage/app/private/pagination/{version}.json`, injects
     `<span class="page-marker">` tags into `source.xhtml` / `target.xhtml` at
     the recorded offsets, and saves the updated files.
   - Comparison-scoped progress is written to
     `storage/app/tmp/pager/comparisons/{comparison_id}.json`; the UI polls this
     endpoint to show queued → running → done per role (source/target).

5. **Optional publication**
   - Use the “Publier” button to copy comparison artefacts into
     `public/uploads/{author}/{work}/{comparison_folder}` for the legacy site.

### Background jobs

- Pagination jobs (`ApplyLignesJob`, `InjectComparisonPaginationJob`) run on
  the `page-markers` queue; facsimile processing uses the `facsimiles` queue.
- `laravel-queue` container runs  
  `php artisan queue:work --queue=facsimiles,page-markers`.
- Manual execution:  
  `docker compose exec laravel php artisan queue:work --queue=page-markers --stop-when-empty`.

### Artefact cheat sheet

| Artefact                         | Location                                                          |
|---------------------------------|-------------------------------------------------------------------|
| Uploaded TXT                    | `storage/app/public/uploads/versions/{folder}.txt`                |
| TEI version                     | `storage/app/public/uploads/versions/{folder}.xml`                |
| `_lignes` raw file              | `storage/app/private/lignes/{version_id}.txt`                     |
| Pagination sidecar              | `storage/app/private/pagination/{version_id}.json`                |
| Version progress                | `storage/app/tmp/pager/{version_id}.json`                         |
| Comparison progress             | `storage/app/tmp/pager/comparisons/{comparison_id}.json`          |
| Medite outputs (XHTML/TEI)      | `storage/app/public/uploads/{author}/{work}/comparisons/{id}`     |
| Published comparison (optional) | `public/uploads/{author}/{work}/{comparison_folder}`              |
| Facsimile images (draft)        | `storage/app/public/uploads/{author}/{work}/{version}/`           |

---

## Internals

For deeper dives check:

- `laravel/app/Services/PageMarkerService.php`
- `laravel/app/Jobs/ApplyLignesJob.php`
- `laravel/app/Jobs/InjectComparisonPaginationJob.php`
- `medite/app/flask_app.py`

Happy comparing! :)

Happy comparing! :)
