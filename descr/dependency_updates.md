# Dependency Update Process

This document defines the future regular dependency update process for Variance.

## Scope

Variance currently has five dependency surfaces that need regular review:

- Laravel Composer dependencies in `laravel/composer.json`
- Laravel front-end dependencies in `laravel/package.json`
- Medite Python dependencies in `medite/app/variance/pyproject.toml`
- Legacy PHP dependencies in `variance/composer.json`
- Container and workflow dependencies in Dockerfiles, Compose files, and GitHub Actions workflows

## Repository Automation

The repository now uses `.github/dependabot.yml` to open **monthly** update pull requests against the `development` branch.

Two PR streams are defined:

- `application`: Laravel Composer, Laravel npm, Medite Python, and legacy PHP Composer dependencies
- `infrastructure`: Dockerfiles, Docker Compose image tags, and GitHub Actions versions

The configuration is intentionally conservative:

- Runs monthly at fixed Zurich times
- Groups updates so the team reviews at most one application PR and one infrastructure PR per cycle
- Ignores major-version updates in the regular monthly flow
- Leaves runtime constraints such as `php` and `python` unchanged

Major framework/runtime upgrades stay separate on purpose. For Variance, they should be handled as dedicated requests because they can affect Laravel, Medite, container images, and staging rollout together.

Current adopted Laravel runtime baseline:

- PHP runtime line: `8.3`
- Node runtime line: `22`

Runtime policy for future monthly updates:

- Keep monthly PRs on the currently adopted runtime lines and accept patch/minor updates within those lines
- Revisit the next PHP runtime line with a dedicated request once the current line is stable on staging and before it falls too far behind PHP active support
- For Variance specifically, the next PHP runtime target should be `8.4`, but not as part of the ordinary monthly dependency PRs

## Validation Gates

Regular update PRs should be validated in Docker-backed environments, not against host-installed tools.

Automated checks:

- `dependency-checks.yml` validates compose files, runs Laravel PHPUnit, builds Laravel front-end assets, runs Medite tests, validates the legacy Composer manifest, and builds all three application images

Notes:

- The Medite pytest suite is intentionally kept as a real integration gate and can take several minutes on the larger XML fixtures
- The legacy Composer job validates the manifest only; the legacy runtime is still exercised primarily through Docker image builds and application smoke tests

Local validation commands:

```bash
docker compose config
docker compose -f docker-compose.vm.yml config
docker compose exec -T laravel sh -lc 'cd /var/www/html && composer install --no-interaction --prefer-dist && composer validate --no-check-publish && composer test'
docker compose exec -T laravel sh -lc 'cd /var/www/html && npm install && npm run build'
docker compose exec -T medite sh -lc 'cd /app/variance && poetry install --with dev --no-interaction --no-ansi && pytest'
docker compose exec -T variance-app sh -lc 'cd /var/www && composer validate --no-check-publish'
curl -I http://localhost:8080/
curl -I http://localhost:8080/admin/
curl http://localhost:8080/health
```

If a PR changes Dockerfiles or image tags, also rebuild locally:

```bash
docker compose build laravel laravel-queue laravel-scheduler medite variance-app
docker compose up -d
```

## Monthly Operating Procedure

Use this sequence for the normal monthly cycle:

1. Let Dependabot open the `application` and `infrastructure` PRs against `development`.
2. Review release notes and lockfile changes before merging.
3. Merge the lower-risk PR first:
   `infrastructure` when it is limited to patch/minor base image or action bumps, otherwise `application` first.
4. Run the local validation commands if CI exposes any ambiguity, especially around Medite and container rebuilds.
5. After merge to `development`, let the existing stage image workflows publish fresh stage images.
6. Deploy staging from `development` using the standard VM procedure in `descr/deployment_notes.md`.
7. Smoke test `/`, `/admin/`, and `/health` on staging before promoting the branch toward `main`.

## Manual Follow-up Items

Some updates remain intentionally outside the automated monthly flow:

- Laravel major upgrades
- PHP major upgrades for `laravel/Dockerfile` or `variance/docker/Dockerfile`
- Python major upgrades for Medite
- MariaDB/Redis/Nginx/Apache major tag changes in Compose
- Any dependency update that requires schema changes, data migration, or public output re-generation

Those should be opened as explicit Variance requests with their own validation and rollback plan.

## Reproducibility Note

Medite now needs a committed `poetry.lock`. Without it, the Python dependency set drifts every time Poetry resolves from `pyproject.toml`, which is not compatible with a stable monthly update process.

## What Dependabot Does Not Cover Cleanly

- Internal `unillett/*:*-latest` images in Compose are part of the Variance release pipeline, not third-party dependency management
- Host packages on the staging VM
- Manual Laravel environment changes in `laravel.env`
- Database engine upgrades that require dump/restore planning
