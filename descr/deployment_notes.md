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

---

These notes should complement your organisation’s deployment standards; adapt paths and practices to your infrastructure.
