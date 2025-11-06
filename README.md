# Variance & Medite Integration

This project automates and provides an admin UI to produce the legacy
Variance publish files. Editors can upload textual versions, generate
Medite comparisons, manage facsimiles, and assemble the published asset
bundle with pagination markers derived from `_lignes` files. In practice
this means:

- One place to manage versions, `_lignes`, facsimiles, and comparison manifests.
- Automatic Medite runs with pagination injection feedback per role.
- A single “Export” action that delivers the legacy-ready bundle (comparison +
  manifest-selected facsimiles only).

The project is split into:

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
   git clone https://github.com/unil-lettres/variance-input.git
   cd variance-input
   docker compose up -d --build
   ```

   This brings up:
   - `laravel` (admin web app at http://localhost:8080/admin via the proxy)
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

- **Upload version** – “Téléverser une version” in the **Versions** card
  generates the TEI container under `storage/app/public/uploads/versions/`.

- **Attach pagination (`_lignes`)** – Drop the `_lignes` text file; the queue
  produces `storage/app/private/pagination/{version}.json` sidecars that
  describe every pagination marker.

- **Import & curate facsimiles** – Upload batches of images (optional) and use
  the **Fac-similés** manifest manager to choose which files belong to each
  comparison (JSON manifests stay in sync with the comparisons table pills).

- **Run Medite** – Launch a comparison from the **Comparaisons** card. Medite
  writes its XHTML/TEI components to
  `storage/app/public/uploads/{author}/{work}/comparisons/{id}`.

- **Inject pagination** – Use “Injecter la pagination” per role (source/target)
  to merge the `_lignes` markers into the Medite XHTML files.

- **Export legacy bundle** – Click “Exporter” to download a zip containing the
  published comparison folder plus only the facsimiles referenced in the JSON
  manifests (source/target).

- **Publish** – Optional. Keep the comparison synced to the legacy public tree
  via the “Publier” toggle.

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

3. **Curate facsimiles & manifests**
   - Upload image batches via the facsimile carousel; jobs normalise the
     originals (`img_*`) and generate thumbnails.
   - The manifest manager lists every comparison (source/target). Selecting a
     comparison highlights the images currently published in its JSON manifest.
   - Changes are written back to
     `storage/app/public/uploads/{author}/{work}/{version}/images_{role}_{author--work--comparison}.json`
     and reflected immediately in the comparisons table (“JSON” pill).

4. **Run Medite**
   - Launch from the comparisons table. `MediteController::runMedite` calls the
     Flask service which executes the Celery task.
   - Medite writes TEI diff + XHTML components under
     `/app/uploads/{author}/{work}/comparisons/{comparison_id}` which Laravel
     mirrors into `storage/app/public/uploads/{author}/{work}/comparisons/{id}`.
   - Comparison metadata is stored/updated in the DB.

5. **Inject pagination markers**
   - Click “Injecter la pagination”. `ComparisonController::applyPageMarkers`
     ensures both versions have sidecars, marks the comparison queued, and
     dispatches `InjectComparisonPaginationJob`.
   - The job loads `storage/app/private/pagination/{version}.json`, injects
     `<span class="page-marker">` tags into `source.xhtml` / `target.xhtml` at
     the recorded offsets, and saves the updated files.
   - Comparison-scoped progress is written to
     `storage/app/tmp/pager/comparisons/{comparison_id}.json`; the UI polls this
     endpoint to show queued → running → done per role (source/target).

6. **Export the legacy bundle**
   - “Exporter” generates a zip containing the published comparison directory
     plus, for each role, only the facsimile images referenced in the manifest
     JSON (and the manifest itself).

7. **Optional publication**
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
| Facsimile manifest JSON         | `storage/app/public/uploads/{author}/{work}/{version}/images_{role}_{author--work--comparison}.json` |
| Version progress                | `storage/app/tmp/pager/{version_id}.json`                         |
| Comparison progress             | `storage/app/tmp/pager/comparisons/{comparison_id}.json`          |
| Medite outputs (XHTML/TEI)      | `storage/app/public/uploads/{author}/{work}/comparisons/{id}`     |
| Published comparison (optional) | `public/uploads/{author}/{work}/{comparison_folder}`              |
| Facsimile images (draft)        | `storage/app/public/uploads/{author}/{work}/{version}/`           |
| Exported legacy zip             | Downloaded on demand via `/comparisons/{id}/export`                |

---

## Internals

For deeper dives check:

- `laravel/app/Services/PageMarkerService.php`
- `laravel/app/Jobs/ApplyLignesJob.php`
- `laravel/app/Jobs/InjectComparisonPaginationJob.php`
- `medite/app/flask_app.py`

Happy comparing! :)
