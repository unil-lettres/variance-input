# Variance Architecture Overview

Variance ships as a multi-container stack that combines modern Laravel admin tooling (versions, pagination, facsimile manifest manager, legacy export, health reporting), the Medite alignment engine, and the legacy public-facing PHP site. This document explains how the pieces fit together and what each container is responsible for.

---

## Container Topology

```
+------------------+
| variance-proxy   |
| (nginx, :8080)   |
+--------+---------+
         | /admin, /health
         v
   +-----------+
   | laravel   |
   | php-fpm   |
   +-----------+
         ^
         |
   +-------------------+
   | laravel-queue     |
   | laravel-scheduler |
   +-------------------+
         ^
         |
   +-----------+        +--------------------+
   | mariadb   |        | redis              |
   | database  |        | Laravel cache/queue|
   +-----------+        | + Celery broker    |
                        +---------+----------+
                                  |
                                  v
                          +---------------+
                          | medite        |
                          | Flask + Celery|
                          | :5000         |
                          +---------------+

+------------------+
| variance-proxy   |
| /                |
+--------+---------+
         |
         v
   +---------------+       +----------------+
   | variance-web  | ----> | variance-app   |
   | Apache        | FCGI  | PHP-FPM legacy |
   +---------------+       +----------------+
```

### variance-proxy (Nginx)
* Exposes the overall platform on a single port (`localhost:8080`).
* Routes requests:
  * `/admin` → `laravel` container
  * `/health` → `laravel` container
  * `/` → `variance-web` (legacy site)
  * `/medite` → `medite` Flask service
* Terminates HTTP connections locally; no TLS in dev by default.

### laravel
* Runs the Laravel admin panel behind `php-fpm` on port `9000` (internal to the Docker network).
* Handles:
  * Version uploads (`VersionController`).
  * `_lignes` ingestion and pagination sidecar generation (`PageMarkerService`, `ApplyLignesJob`).
  * Medite orchestration (`MediteController`).
  * Comparison management, pagination injection, legacy export (`ComparisonController`, `InjectComparisonPaginationJob`).
  * Facsimile ingestion plus manifest curation (JSON manager + selection events).
  * Health JSON + admin health report (`HealthController`).
* Shares uploaded assets via bind mounts (`variance_data`, `variance/uploads`, etc.).

### laravel-queue
* Dedicated queue worker container executing multiple
  `php artisan queue:work --queue=facsimiles,page-markers,exports` processes via `laravel/scripts/run-queue-workers.sh`.
* Processes:
  * `ApplyLignesJob` (build sidecar from `_lignes`).
  * `InjectComparisonPaginationJob` (add pagination markers to comparison XHTML).
  * `GenerateLegacyExportJob` (build legacy comparison zips).
  * Facsimile batch processing jobs.

### laravel-scheduler
* Dedicated scheduler container executing `php artisan schedule:run` once per minute.
* Required for scheduled maintenance/heartbeat tasks such as `health:scheduler-heartbeat`.
* Writes the scheduler heartbeat file consumed by the health report.

### medite
* Flask + Celery app that wraps the Medite alignment engine.
* Endpoints:
  * `/run_diff2` – called by Laravel to queue alignment jobs.
  * `/task_status/{id}` – job polling.
* Uses Celery workers to run `scripts/diff.py` (TEI/XHTML generation).
* Shares file system roots (`variance_data`, `uploads`) with Laravel so the outputs appear instantly.

### variance-web
* Legacy Apache frontend serving the public site.
* For dynamic PHP requests, proxies to `variance-app` (legacy PHP-FPM container).
* Reads legacy-facing assets from the shared upload trees.

### variance-app
* Legacy PHP-FPM runtime used by `variance-web`.
* Runs the historical public PHP codebase and legacy templates.
* Shares the same upload roots as `variance-web`.

### Supporting services
* **mariadb** – Primary database for Laravel/legacy PHP and the default Laravel queue backend in current deployments.
* **redis** – Celery broker/backend for Medite; can also be used by Laravel if queue/cache config is changed.

---

## Shared Volumes / Data Flow

| Path in compose project                  | Mounted into containers                    | Purpose                              |
|------------------------------------------|--------------------------------------------|--------------------------------------|
| `./laravel`                              | `laravel`, `laravel-queue`, `laravel-scheduler` | Laravel source tree in local dev. |
| `./laravel/storage/app/public`           | `laravel`, `laravel-queue`, `laravel-scheduler`, `medite`, `variance-proxy` | Laravel public storage, including `/storage/...` assets. |
| `uploads` Docker volume / `var/uploads` on VM | `laravel*`, `medite`, `variance-web`, `variance-app` | Main upload tree: versions, comparisons, facsimiles, manifests. |
| `uploads_images` Docker volume / `var/uploads_images` on VM | `laravel*`, `variance-web`, `variance-app` | Work cover images. |
| `variance/uploads/pdf` locally / `var/uploads_pdf` on VM | `laravel*`, `variance-web`, `variance-app` | Work notice PDFs. |
| `variance_data/` locally / `var/variance_data` on VM | `laravel*`, `medite` | Private shared staging/runtime area for Medite-related data. |
| `./medite/app/...` (local dev only)      | `medite`                                   | Flask app and Medite engine code. |
| `./variance`                             | `variance-web`, `variance-app`             | Legacy public codebase. |

Key data flow:
1. Laravel stores private sidecars under `storage/app/private/...` and public artifacts under `storage/app/public/...` / `public/uploads`.
2. Facsimile uploads are normalized and curated via JSON manifests tied to comparisons.
3. Medite reads staged inputs from shared mounts and writes comparison outputs back into the shared upload/public trees.
4. Queue jobs inject pagination, process facsimiles, and build export bundles.
5. `variance-proxy` exposes one frontend entry point; `variance-web` + `variance-app` serve the legacy public site.

---

## Network & Access

* All services are connected to the `variance` Docker network (internal DNS).
* Default exposed ports:
  * `8080` – `variance-proxy` (primary entry point)
  * `5000` – Medite Flask app (useful for debugging raw endpoints)
  * `6379` – Redis (intentionally published for local tooling)
  * `3306` – MariaDB (published for local DB clients)
  * `127.0.0.1:8282` – `variance-web` (legacy Apache, local debugging only)

> In normal usage, developers only need `localhost:8080` and `localhost:5000`
  (for debugging). All other services communicate internally.

---

## Component Responsibilities

| Component         | Responsibilities |
|-------------------|------------------|
| **Laravel**       | Admin UI, version ingestion, pagination sidecar creation, Medite orchestration, comparison management, facsimile manifest curation, legacy export bundles, API routes, artisan tooling. |
| **Laravel queue** | Executes queued jobs (pagination, facsimiles, exports) to keep HTTP requests non-blocking. |
| **Laravel scheduler** | Runs scheduled commands such as health heartbeats once per minute. |
| **Medite**        | Heavy alignment tasks; transforms TEI versions into comparison artefacts (TEI diff, XHTML fragments). |
| **variance-proxy**| Developer-friendly entry point, simplifies URL routing, keeps legacy + modern apps accessible via one port, and exposes Laravel `/health` publicly. |
| **variance-web**  | Legacy Apache frontend, serving static/public routes and forwarding PHP execution to `variance-app`. |
| **variance-app**  | Legacy PHP-FPM runtime for the historical public site. |
| **MariaDB / Redis** | Data persistence (MariaDB) and queue/backplane support (Redis for Medite/Celery). |

---

## Operational Notes

* Queue jobs are essential: without the `laravel-queue` container running, `_lignes` uploads won’t produce sidecars, exports won’t complete, and pagination injection will stall.
* The scheduler container is also essential for a healthy `/health` report; without it the scheduler heartbeat degrades the global status.
* Export bundles rely on manifest JSON; keep the facsimile manager up to date or the download will contain no images.
* `variance-proxy` is optional in theory but recommended—bypassing it requires juggling multiple ports.
* The legacy public stack (`variance-web` + `variance-app`) reads the same upload roots as Laravel; write access should remain concentrated on Laravel/admin workflows.
* For production/hardening:
  * Add TLS termination (either in `variance-proxy` or behind an external reverse proxy).
  * Consider splitting Redis usage (separate DBs or instances) for Laravel vs. Celery.
  * Persist VM bind mounts / Docker volumes (`dbdata`, uploads, Laravel storage, `variance_data`) outside ephemeral containers.

---

## Where to Look Next

* Docker definitions – `docker-compose.yml`, `nginx/default.conf`, `variance/docker/...`.
* Laravel job orchestration – `laravel/app/Services/PageMarkerService.php`.
* Medite runner – `medite/app/flask_app.py`, `medite/app/variance/scripts/diff.py`.

This architecture lets Laravel coordinate uploads and metadata, Medite handle compute-heavy diffing, and the proxy container present a single interface for both admin and public viewers.
