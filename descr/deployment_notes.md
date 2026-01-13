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

## Legacy PDF notices (work ID remap)

If notices point to the wrong PDF after import, overwrite the new-ID PDFs with
the legacy PDFs using the work ID map. Run on the VM (no access to legacy prod
required):

```bash
python3 - <<'PY'
import json, os, shutil, hashlib, datetime
map_path = '/var/www/variance-input/var/laravel_storage/app/private/legacy_import/work_id_map.json'
pdf_dir = '/var/www/variance-input/var/uploads_pdf'
backup_dir = '/var/www/variance-input/var/uploads_pdf_backup_' + datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
os.makedirs(backup_dir, exist_ok=True)

def md5(path):
    h = hashlib.md5()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()

updated = skipped = missing = 0
with open(map_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

for entry in data:
    legacy_id = str(entry['legacy_id'])
    new_id = str(entry['new_id'])
    legacy_pdf = os.path.join(pdf_dir, legacy_id + '.pdf')
    new_pdf = os.path.join(pdf_dir, new_id + '.pdf')
    if not os.path.isfile(legacy_pdf):
        missing += 1
        continue
    if os.path.isfile(new_pdf) and md5(legacy_pdf) == md5(new_pdf):
        skipped += 1
        continue
    if os.path.isfile(new_pdf):
        shutil.copy2(new_pdf, os.path.join(backup_dir, new_id + '.pdf'))
    shutil.copy2(legacy_pdf, new_pdf)
    updated += 1

print(f"updated={updated} skipped_identical={skipped} missing_legacy={missing}")
print(f"backup_dir={backup_dir}")
PY
```

## Disk space recovery (VM)

If `/var` fills up during large legacy syncs, reclaim space from unused Docker
artifacts first:

```bash
docker image prune -a -f
docker builder prune -a -f
docker volume ls -qf dangling=true
docker volume rm $(docker volume ls -qf dangling=true)
df -h /var
```

Avoid pruning non-dangling volumes unless you have confirmed they are not used
by MariaDB/Redis.

---

These notes should complement your organisation’s deployment standards; adapt paths and practices to your infrastructure.
