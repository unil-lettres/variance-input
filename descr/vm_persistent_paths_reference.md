# Persistent Paths on `plt-tst-1`

Reference date: April 17, 2026.

This document inventories the persistent file paths used on the staging VM `plt-tst-1` from:

- [`docker-compose.vm.yml`](/Users/jganivet/Développement/variance2/docker-compose.vm.yml)
- [`nginx/default.conf`](/Users/jganivet/Développement/variance2/nginx/default.conf)
- [`laravel/entrypoint.sh`](/Users/jganivet/Développement/variance2/laravel/entrypoint.sh)
- [`medite/app/flask_app.py`](/Users/jganivet/Développement/variance2/medite/app/flask_app.py)
- Laravel controllers and services that read/write uploads, private sidecars, manifests, exports, and reader cache

The VM deploy root is:

- `/var/www/variance-input`

The persistent runtime subtree currently present on the VM is:

- `/var/www/variance-input/var/dbdata`
- `/var/www/variance-input/var/laravel_storage`
- `/var/www/variance-input/var/uploads`
- `/var/www/variance-input/var/uploads_images`
- `/var/www/variance-input/var/uploads_pdf`
- `/var/www/variance-input/var/variance_data`

Observed sizes on April 17, 2026:

- `var/dbdata`: `155M`
- `var/laravel_storage`: `2.1G`
- `var/uploads`: `20G`
- `var/uploads_images`: `13M`
- `var/uploads_pdf`: `17M`
- `var/variance_data`: `0`

## Summary

For a full staging backup oriented toward restoration of editorial data, the important sources are:

1. Database dump from `mariadb`
2. `var/uploads`
3. `var/uploads_images`
4. `var/uploads_pdf`
5. `var/laravel_storage/app/private`
6. `var/laravel_storage/app/public/uploads`
7. `var/variance_data`

`var/dbdata` is the physical MariaDB datadir, but a logical SQL dump is the safer backup artifact while the service is running.

## Container Mount Map

### `mariadb`

- Host path: `/var/www/variance-input/var/dbdata`
- Container path: `/var/lib/mysql`
- Purpose:
  - MariaDB physical data files for the `variance` database
  - system schemas: `mysql`, `performance_schema`, `sys`
- Used by:
  - `mariadb`
- Backup note:
  - prefer logical dump (`mariadb-dump`) over copying live InnoDB files

### `laravel`, `laravel-queue`, `laravel-scheduler`

- Host path: `/var/www/variance-input/var/laravel_storage`
- Container path: `/var/www/html/storage`
- Purpose:
  - Laravel `storage/` tree, including:
    - `storage/app/private/lignes`
    - `storage/app/private/pagination`
    - `storage/app/private/reader_cache`
    - `storage/app/private/exports`
    - `storage/app/private/facsimile_backups`
    - `storage/app/private/facsimile_queue`
    - `storage/app/private/cache/version-editor`
    - `storage/app/private/legacy_import`
    - `storage/app/public/uploads`
    - `storage/logs`
    - framework caches/views/sessions
- Used by:
  - `laravel`
  - `laravel-queue`
  - `laravel-scheduler`
- Why it matters:
  - contains private editorial sidecars and reader artifacts not mirrored under `var/uploads`
  - also contains Laravel-public artifacts exposed through `/storage/`

### `laravel`, `laravel-queue`, `laravel-scheduler`

- Host path: `/var/www/variance-input/var/variance_data`
- Container path: `/var/www/html/storage/app/private/variance_data`
- Purpose:
  - shared private staging area for Medite inputs or other temporary private assets
- Used by:
  - `laravel`
  - `laravel-queue`
  - `laravel-scheduler`

### `laravel`, `laravel-queue`, `laravel-scheduler`

- Host path: `/var/www/variance-input/var/uploads`
- Container paths:
  - `/var/www/html/public/uploads`
  - `/var/www/variance/uploads`
- Purpose:
  - public upload root used by Laravel and mirrored legacy PHP paths
  - contains:
    - `uploads/versions/*.txt`
    - `uploads/versions/*.xml`
    - comparison directories
    - published comparison trees
    - facsimile directories
    - JSON manifests
    - legacy-compatible public assets read by the PHP site
- Used by:
  - `laravel`
  - `laravel-queue`
  - `laravel-scheduler`
  - `variance-web`
  - `variance-app`
  - `medite`
- Why it matters:
  - this is the single most important asset tree on staging

### `laravel`, `laravel-queue`, `laravel-scheduler`

- Host path: `/var/www/variance-input/var/uploads_images`
- Container paths:
  - `/var/www/html/public/uploads_images`
  - `/var/www/variance/uploads_images`
  - `/var/www/uploads_images`
- Purpose:
  - work cover images displayed in the public catalogue
- Used by:
  - `laravel`
  - `laravel-queue`
  - `laravel-scheduler`
  - `variance-web`
  - `variance-app`

### `laravel`, `laravel-queue`, `laravel-scheduler`

- Host path: `/var/www/variance-input/var/uploads_pdf`
- Container paths:
  - `/var/www/variance/uploads/pdf`
  - `/var/www/uploads/pdf`
- Purpose:
  - work notice PDFs
- Used by:
  - `laravel`
  - `laravel-queue`
  - `laravel-scheduler`
  - `variance-web`
  - `variance-app`

### `medite`

- Host path: `/var/www/variance-input/var/uploads`
- Container path: `/app/uploads`
- Purpose:
  - Medite input/output comparison directory root
  - generated XML/XHTML comparison artifacts
- Used by:
  - `medite`

### `medite`

- Host path: `/var/www/variance-input/var/variance_data`
- Container path: `/app/variance_data`
- Purpose:
  - shared private area for staged Medite inputs
- Used by:
  - `medite`

### `medite`

- Host path: `/var/www/variance-input/var/laravel_storage/app/public`
- Container path: `/app/storage_public`
- Purpose:
  - Medite copies comparison outputs into Laravel-public storage under `/app/storage_public/uploads/...`
- Used by:
  - `medite`

### `variance-proxy`

- Host path: `/var/www/variance-input/var/laravel_storage/app/public`
- Container path: `/var/www/html/storage/app/public`
- Purpose:
  - public static alias for `/storage/...`
- Used by:
  - `variance-proxy`

## Important Subpaths by Function

### Version source texts

- Host path:
  - `/var/www/variance-input/var/uploads/versions/{folder}.txt`
- Read by:
  - Laravel version download and reader build
  - Medite staging logic via Laravel
- Code references:
  - `VersionController.php` text availability/download/build paths

### Version XML / TEI

- Host path:
  - `/var/www/variance-input/var/uploads/versions/{folder}.xml`
- Read by:
  - Laravel version download
  - pagination-from-`<pb>` workflows

### Version Medite intermediate text

- Host path:
  - `/var/www/variance-input/var/uploads/versions/{folder}.medite.txt`
- Read/write by:
  - Laravel comparison workflows

### Facsimiles

- Host path:
  - `/var/www/variance-input/var/uploads/{author_folder}/{work_folder}/{version_folder}/`
- Contains:
  - master images
  - thumbnails
  - comparison image manifests such as `images_source_*.json` / `images_target_*.json`
- Used by:
  - Laravel admin viewer
  - publication
  - legacy public site

### Comparison outputs, unpublished/dev

- Host path:
  - `/var/www/variance-input/var/uploads/{author_folder}/{work_folder}/comparisons/{comparison_id}/`
- Contains:
  - `source.xhtml`
  - `target.xhtml`
  - `d.xhtml`, `i.xhtml`, `r.xhtml`, `s.xhtml`
  - manifest JSON copies as applicable
- Produced by:
  - Medite
  - Laravel publication helpers

### Comparison outputs, published/prod-style

- Host path:
  - `/var/www/variance-input/var/uploads/{author_folder}/{work_folder}/{comparison_folder}/`
- Used by:
  - legacy public publication paths
  - publish/depublish workflows

### Work cover images

- Host path:
  - `/var/www/variance-input/var/uploads_images/{hash}.{ext}`
- Used by:
  - legacy/public catalogue
  - Laravel work edit workflows

### Work PDFs

- Host path:
  - `/var/www/variance-input/var/uploads_pdf/{work_id}.pdf`
- Used by:
  - public work pages
  - Laravel work edit workflows

### `_lignes`

- Host path:
  - `/var/www/variance-input/var/laravel_storage/app/private/lignes/{version_id}.txt`
- Used by:
  - `ApplyLignesJob`
  - page marker extraction and status APIs

### Pagination sidecars

- Host path:
  - `/var/www/variance-input/var/laravel_storage/app/private/pagination/{version_id}.json`
- Used by:
  - reader build
  - viewer synchronization
  - comparison pagination injection workflows

### Reader cache

- Host path:
  - `/var/www/variance-input/var/laravel_storage/app/private/reader_cache/{version_id}/...`
- Used by:
  - viewer warm-up and reader dataset persistence

### Legacy export artifacts

- Host path:
  - `/var/www/variance-input/var/laravel_storage/app/private/exports/comparisons/...`
- Used by:
  - legacy export queue/download flow

### Facsimile backup / queue staging

- Host paths:
  - `/var/www/variance-input/var/laravel_storage/app/private/facsimile_backups/...`
  - `/var/www/variance-input/var/laravel_storage/app/private/facsimile_queue/...`
- Used by:
  - facsimile import / cancel / restore flows

### Laravel-public comparison copies

- Host path:
  - `/var/www/variance-input/var/laravel_storage/app/public/uploads/...`
- Used by:
  - Nginx `/storage/` alias
  - Medite copy-out path
  - some comparison export/public access flows

## Paths Exposed via HTTP

These are not separate backup roots; they are HTTP views over the same persisted data:

- `/storage/...`
  - served by nginx from `/var/www/variance-input/var/laravel_storage/app/public`
- `/uploads/...`
  - served by legacy PHP from `/var/www/variance-input/var/uploads`
- `/uploads_images/...`
  - served by legacy PHP from `/var/www/variance-input/var/uploads_images`
- `/uploads/pdf/...`
  - served by legacy PHP from `/var/www/variance-input/var/uploads_pdf`

## Recommended Read-Only Backup Set

For a local Mac backup before deployment, the recommended sources are:

1. Logical database dump:
   - source service: `mariadb`
   - database: `variance`
2. `/var/www/variance-input/var/uploads`
3. `/var/www/variance-input/var/uploads_images`
4. `/var/www/variance-input/var/uploads_pdf`
5. `/var/www/variance-input/var/laravel_storage/app/private`
6. `/var/www/variance-input/var/laravel_storage/app/public/uploads`
7. `/var/www/variance-input/var/variance_data`

For rollback or VM reconstruction, also capture the VM-side configuration files that are bind-mounted into running containers:

1. `/var/www/variance-input/docker-compose.vm.yml`
2. `/var/www/variance-input/laravel.env`
3. `/var/www/variance-input/variance/.env`
4. `/var/www/variance-input/nginx/default.conf`
5. `/var/www/variance-input/laravel/entrypoint.sh`
6. `/var/www/variance-input/laravel/app/Providers/AppServiceProvider.php`
7. `/var/www/variance-input/variance/docker/config/variance.conf`
8. `/var/www/variance-input/variance/docker/config/variance.ini`

And capture runtime metadata for traceability:

1. `docker compose -f docker-compose.vm.yml ps`
2. `docker compose -f docker-compose.vm.yml images`
3. Image IDs / digests for the currently running tags
4. Optionally, exported Docker images if a fully self-contained rollback archive is required

## Backup Notes

- Do not copy `var/dbdata` as a live filesystem snapshot unless the DB is stopped.
- Prefer `mariadb-dump` streamed to the Mac.
- `var/laravel_storage/framework/*` and `storage/logs/*` are runtime caches/logs, not primary editorial assets.
- The editorially important part of `var/laravel_storage` is mainly:
  - `app/private`
  - `app/public/uploads`
- Redis is not persisted in `docker-compose.vm.yml`, so queue/cache transient state is not part of the durable backup set.
- Because staging uses mutable image tags such as `laravel-stage-latest` and `latest`, a plain list of tags is not enough for exact rollback. Preserve image IDs at minimum, and export image tarballs when you need rollback independence from the remote registry.
