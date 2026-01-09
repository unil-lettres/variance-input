# Deployment Notes

Guidance for running the Variance stack outside the default development setup.

---

## Volumes & Persistence

- **Database** (`dbdata/`) – Persist MariaDB data outside the repo via a named volume or bind mount.
- **Uploads / runtime data** (`variance_data/`, `variance/uploads/`) – Store on a durable volume; Laravel and Medite rely on shared access.
- **Logs** – Consider directing Laravel logs (`laravel/storage/logs`) to a log aggregator in production.

## Environment

- Copy `laravel/.env.example` to `laravel/.env` and set:
  * `APP_ENV=production`
  * `APP_URL` to your external URL.
  * Database credentials matching your MariaDB deployment.
  * `QUEUE_CONNECTION=redis` (default) and ensure Redis is reachable.
  * Update mail settings if emailing is required.
- For Medite, adjust `.env` or environment variables as needed (e.g. concurrency, Celery broker/backends).

## Networking / Proxy

- In production, terminate TLS either in `variance-proxy` or via an upstream load balancer.
- Update `nginx/default.conf` to match your hostnames, SSL certificates, and desired routes.
- Optionally expose Medite on a separate hostname or keep it internal-only.
- If TLS is terminated upstream (e.g. Apache), forward `X-Forwarded-Proto` so Laravel
  generates HTTPS URLs correctly (or you will see http:// redirects from `/admin`).

## Scaling

- Increase Celery worker count in the `medite` container for faster diff processing.
- Run multiple queue workers (`laravel-queue` replicas) if pagination/facsimile jobs backlog.
- Use horizontal scaling cautiously; shared storage must support concurrent writes.

## Monitoring

- Track queue length (Laravel Horizon or custom metrics) and Celery task times.
- Monitor disk usage for upload directories.
- Keep an eye on Laravel logs (`docker compose logs -f laravel`) and queue logs.

## Backups

- Database dumps (MariaDB).
- Uploads (versions, facsimiles, sidecars) – copy `variance_data` + `variance/uploads` to durable storage.

## Security Checklist

- Enforce HTTPS for admin access.
- Protect `variance-proxy` with appropriate auth/IP restrictions if exposed.
- Rotate application keys and database credentials regularly.
- Disable or restrict direct container shell access on production hosts.

## Legacy (PHP) setup checklist

- Copy `/var/www/variance-input/variance/php/settings.inc.example.php` to
  `/var/www/variance-input/variance/php/settings.inc.php` on the host. This file is
  git-ignored and required by public comparison pages.
- Ensure legacy PHP dependencies exist (needed for `vendor/autoload.php`). If missing,
  run:
  ```bash
  docker compose -f docker-compose.vm.yml exec -T variance-app \
    sh -lc 'cd /var/www && composer install --no-dev --optimize-autoloader'
  ```

## Legacy data import (one-time)

- Copy legacy assets to the new host (exclude any deprecated folders):
  ```bash
  rsync -a --exclude 'deprecated' user@legacy-host:/var/www/variance/uploads/ /var/www/variance-input/variance/uploads/
  rsync -a user@legacy-host:/var/www/variance/uploads_images/ /var/www/variance-input/variance/uploads_images/
  rsync -a user@legacy-host:/var/www/variance/uploads/pdf/ /var/www/variance-input/variance/uploads/pdf/
  ```
- Ensure the upload directories are writable by the deployment user:
  ```bash
  sudo chown -R deployer:www-data /var/www/variance-input/variance/uploads \
    /var/www/variance-input/variance/uploads_images \
    /var/www/variance-input/variance/uploads/pdf
  ```
- Copy the legacy SQL dump into the Laravel container and import it:
  ```bash
  docker compose exec -T laravel \
    php artisan variance:import-legacy storage/app/private/legacy_import/legacy.dump.sql
  ```
- The import command writes a work ID map to
  `laravel/storage/app/private/legacy_import/work_id_map.json` for reference and
  copies legacy PDFs to the new work IDs when available.

---

These notes should complement your organisation’s deployment standards; adapt paths and practices to your infrastructure.
