# Variance Architecture Overview

Variance ships as a multi-container stack that combines modern Laravel admin tooling, the Medite alignment engine, and the legacy public-facing PHP site. This document explains how the pieces fit together and what each container is responsible for.

---

## Container Topology

```
+------------------+      +------------------+
|   variance-proxy |<---->|   variance-web   |
| (nginx, :8080)   |      | (Apache + PHP)   |
|        ^         |      +------------------+
|        |                         ^
|        |                         |
|        v                         |
|   laravel            redis <-----+
|  (php-fpm +          (cache, queues)
|   artisan, :8000)           ^
|        ^                    |
|        |                    |
|        v                    |
|   laravel-queue             |
|  (artisan queue)            |
|        ^                    |
|        |                    |
|        v                    |
|   mariadb (data)            |
|                             |
|        v                    |
|   medite (Flask + Celery) --+
|   (:5000 API)               |
+-----------------------------+
```

### variance-proxy (Nginx)
* Exposes the overall platform on a single port (`localhost:8080`).
* Routes requests:
  * `/admin` → `laravel` container
  * `/` → `variance-web` (legacy site)
  * `/medite` or API paths → `medite` flask service (if needed)
* Terminates HTTP connections locally; no TLS in dev by default.

### laravel
* Runs the Laravel admin panel (PHP-FPM + Artisan).
* Handles:
  * Version uploads (`VersionController`).
  * `_lignes` ingestion and pagination sidecar generation (`PageMarkerService`, `ApplyLignesJob`).
  * Medite orchestration (`MediteController`).
  * Comparison management, pagination injection (`ComparisonController`, `InjectComparisonPaginationJob`).
  * Facsimile ingestion/publishing.
* Shares uploaded assets via bind mounts (`variance_data`, `variance/uploads`, etc.).

### laravel-queue
* Dedicated queue worker container executing  
  `php artisan queue:work --queue=facsimiles,page-markers`.
* Processes:
  * `ApplyLignesJob` (build sidecar from `_lignes`).
  * `InjectComparisonPaginationJob` (add pagination markers to comparison XHTML).
  * Facsimile batch processing jobs.

### medite
* Flask + Celery app that wraps the Medite alignment engine.
* Endpoints:
  * `/run_diff2` – called by Laravel to queue alignment jobs.
  * `/task_status/{id}` – job polling.
* Uses Celery workers to run `scripts/diff.py` (TEI/XHTML generation).
* Shares file system roots (`variance_data`, `uploads`) with Laravel so the outputs appear instantly.

### variance-web
* Legacy Apache/PHP site serving public content (read-only in development).
* Consumes uploads prepared by the admin interface (TEI, XHTML, facsimiles, manifests).
* Helpful as a visual check before publishing or for regression testing.

### Supporting services
* **mariadb** – Primary database for Laravel/legacy PHP.
* **redis** – Queue backend for Laravel and Celery backend for Medite.

---

## Shared Volumes / Data Flow

| Path in compose project                  | Mounted into containers                    | Purpose                              |
|------------------------------------------|--------------------------------------------|--------------------------------------|
| `variance_data/`                         | `laravel`, `laravel-queue`, `medite`, `variance-web` | Shared runtime storage (uploads, manifests, pagination JSON). |
| `variance/uploads/`                      | `laravel`, `laravel-queue`, `medite`, `variance-web` | Legacy/public upload tree.           |
| `laravel/storage/app/public`             | Shared with `medite` as `/app/storage_public` | Allow Medite to read TEI versions and mirror outputs. |
| `laravel` source tree                    | `laravel`, `laravel-queue`                 | PHP application code.                |
| `medite/app`                             | `medite`                                   | Python application + Medite engine.  |

Key data flow:
1. Laravel stores versions and sidecars under `laravel/storage/app/...`.
2. Medite reads TEI from that location, writes diffs/XHTML back.
3. Queue jobs inject pagination into the resulting XHTML.
4. variance-proxy allows browsing/updating through one port; variance-web serves the published resources.

---

## Network & Access

* All services are connected to the `variance` Docker network (internal DNS).
* Default exposed ports:
  * `8080` – `variance-proxy` (primary entry point)
  * `8000` – Laravel (if accessed directly)
  * `5000` – Medite Flask app (useful for debugging raw endpoints)
  * `6379` – Redis (intentionally published for local tooling)
  * `3306` – MariaDB (published for local DB clients)

> In normal usage, developers only need `localhost:8080` and `localhost:5000`
  (for debugging). All other services communicate internally.

---

## Component Responsibilities

| Component         | Responsibilities |
|-------------------|------------------|
| **Laravel**       | Admin UI, version ingestion, pagination sidecar creation, Medite orchestration, comparison management, facsimile workflows, API routes, artisan tooling. |
| **Laravel queue** | Executes queued jobs (pagination, facsimiles) to keep HTTP requests non-blocking. |
| **Medite**        | Heavy alignment tasks; transforms TEI versions into comparison artefacts (TEI diff, XHTML fragments). |
| **variance-proxy**| Developer-friendly entry point, simplifies URL routing, keeps legacy + modern apps accessible via one port. |
| **variance-web**  | Legacy read-only frontend consuming generated assets; useful for regression checks. |
| **MariaDB / Redis** | Data persistence (MariaDB) and queue/backplane (Redis). |

---

## Operational Notes

* Queue jobs are essential: without the `laravel-queue` container running, `_lignes` uploads won’t produce sidecars and pagination injection will stall.
* `variance-proxy` is optional in theory but recommended—bypassing it requires juggling multiple ports.
* The legacy PHP site reads from the same storage roots as Laravel; keep that tree mounted read-only (`variance/uploads`) to avoid accidental edits.
* For production/hardening:
  * Add TLS termination (either in `variance-proxy` or behind an external reverse proxy).
  * Consider splitting Redis usage (separate DBs or instances) for Laravel vs. Celery.
  * Persist volumes (`dbdata`, `variance_data`) outside of the repository clone.

---

## Where to Look Next

* Docker definitions – `docker-compose.yml`, `nginx/default.conf`, `variance/docker/...`.
* Laravel job orchestration – `laravel/app/Services/PageMarkerService.php`.
* Medite runner – `medite/app/flask_app.py`, `medite/app/variance/scripts/diff.py`.

This architecture lets Laravel coordinate uploads and metadata, Medite handle compute-heavy diffing, and the proxy container present a single interface for both admin and public viewers.
