# Variance Workflow Overview

This document explains the current end‑to‑end pipeline for ingesting textual
versions, preparing pagination metadata, running Medite, and injecting page
markers into comparison outputs.

---

## 1. Version Upload

1. **User action**  
   Upload a `.txt` version via the UI (`versions` card → “Téléverser une
   version”).

2. **Validation** (`VersionController::store`)  
   - File must be plain text (`text/plain`).  
   - File size limited to 8 MB (`max:8192` in kilobytes).

3. **Storage**
   - Raw upload stored at `storage/app/public/uploads/versions/{base}.txt`.  
   - The controller converts the contents to UTF‑8, normalises whitespace, and
     wraps the text in a TEI skeleton with `<lb/>` markers for every original
     line.  
   - The TEI document is saved to `storage/app/public/uploads/versions/{base}.xml`
     and mirrored to the public tree (`public/uploads/versions/{base}.xml`) for
     the legacy PHP frontend.

4. **Database**  
   A `versions` row is created with the generated folder name (`{sequence}{shortTitle}`).

---

## 2. `_lignes` Upload & Sidecar Generation

1. **User action**  
   Upload the `_lignes` file for a given version (`versions` card → “Importer
   un fichier _lignes”).

2. **Storage**  
   The raw `_lignes` text is copied to `storage/app/private/lignes/{version_id}.txt`.

3. **Sidecar job** (`ApplyLignesJob`)  
   - Dispatched automatically; reads the `_lignes` file, parses each page entry,
     and matches it against the TEI version.  
   - No HTML is modified. Instead, the job produces a sidecar JSON file with
     character offsets for each marker:
     `storage/app/private/pagination/{version_id}.json`.
   - Sidecar metadata includes `marker_count`, `missed_count`, canonical text
     hash, and per‑marker context snippets.

4. **Progress tracking**  
   Version‑level progress is written to `storage/app/tmp/pager/{version_id}.json`
   so the UI can display “sidecar ready” once the job completes.

---

## 3. Running Medite

1. **User action**  
   Launch a comparison from the comparisons card (`Lancer Medite`).

2. **Medite controller** (`MediteController::runMedite`)  
   - Collects the source/target TEI files and run parameters.  
   - Calls the Flask service (`medite` container) which runs the Celery task
     `run_diff_script`.

3. **Medite output**  
   - TEI diff and XHTML components (`source.xhtml`, `target.xhtml`, `d.xhtml`,
     `i.xhtml`, `r.xhtml`, `s.xhtml`) generated under
     `/app/uploads/{author}/{work}/comparisons/{comparison_id}` (container path).
   - Files are mirrored back to Laravel’s storage:
     `storage/app/public/uploads/{author}/{work}/comparisons/{comparison_id}`.
   - Public copies are optionally created when publishing.

4. **Database**  
   A `comparisons` row is inserted/updated with the chosen parameters and the
   generated folder name (e.g. `{source}-{target}-runN`).

---

## 4. Injecting Pagination Markers into Comparisons

1. **User action**  
   Click “Injecter la pagination” on a comparison row.

2. **Controller** (`ComparisonController::applyPageMarkers`)  
   - Verifies that both versions have a pagination sidecar.  
   - Marks the comparison as queued (`PageMarkerService::markComparisonQueued`).  
   - Dispatches `InjectComparisonPaginationJob`.

3. **Job behaviour**
   - For each role (`source`, `target`):
     - Loads `storage/app/private/pagination/{version_id}.json`.  
     - Reads the current XHTML (`source.xhtml` / `target.xhtml`) from the
       comparison directory.  
     - Injects `<span class="page-marker">` nodes at the recorded character
       offsets (optionally clearing/replacing existing markers).  
     - Writes the updated XHTML back to both storage and mirror locations.
   - Comparison‑scoped progress is written to
     `storage/app/tmp/pager/comparisons/{comparison_id}.json`. The front‑end
     polls this file to display per‑role status (queued → running → done).

---

## 5. Optional Publication

When the comparison is ready, the user can publish the components (copies them
to `public/uploads/{author}/{work}/{folder}`) via the “Publier” button. This step
is independent of the pagination workflow.

---

## Background Jobs & Workers

- All pagination work (`ApplyLignesJob`, `InjectComparisonPaginationJob`) runs on
  the `page-markers` queue.  
- The default Docker setup includes a `laravel-queue` service that runs  
  `php artisan queue:work --queue=facsimiles,page-markers`.  
- Developers can also run `docker compose exec laravel php artisan queue:work
  --queue=page-markers` to process jobs manually.

---

## Summary of Key Paths

| Artifact                         | Location                                                          |
|---------------------------------|-------------------------------------------------------------------|
| Uploaded TXT                    | `storage/app/public/uploads/versions/{folder}.txt`                |
| Generated TEI                   | `storage/app/public/uploads/versions/{folder}.xml`                |
| `_lignes` raw file              | `storage/app/private/lignes/{version_id}.txt`                     |
| Pagination sidecar JSON         | `storage/app/private/pagination/{version_id}.json`                |
| Version progress                | `storage/app/tmp/pager/{version_id}.json`                         |
| Comparison progress             | `storage/app/tmp/pager/comparisons/{comparison_id}.json`          |
| Comparison XHTML/TEI            | `storage/app/public/uploads/{author}/{work}/comparisons/{id}`     |
| Published comparison (optional) | `public/uploads/{author}/{work}/{comparison_folder}`              |

This flow keeps the canonical TEI untouched, stores pagination metadata as a
sidecar, and applies markers to comparisons on demand so every run stays
reproducible and auditable.
