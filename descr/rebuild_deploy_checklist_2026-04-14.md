# Rebuild And Deploy Checklist (2026-04-14)

This checklist prepares the next staging rebuild/deploy for `plt-tst-1.unil.ch` so the VM picks up all local fixes made in this session.

## Scope

This deploy must include fresh images for:

- `laravel`
- `medite`

It must also update the VM checkout, because staging still bind-mounts some repo files directly:

- `laravel/public`
- `laravel/entrypoint.sh`
- `laravel/app/Providers/AppServiceProvider.php`
- `laravel.env`

## Fixes Included In Local Repo

### Reader / VersionController

- Reader endpoints moved onto authenticated web middleware.
- UTF-8 conversion now respects the normal version editability guard.
- Reader artifacts are cleared correctly after pagination/text mutations.

Files:

- `laravel/app/Http/Controllers/VersionController.php`
- `laravel/routes/web.php`
- `laravel/routes/api.php`
- `laravel/tests/Feature/Workflow/VersionReaderWorkflowTest.php`

### Editor performance

- Version editor payloads are cached even when no pagination sidecar exists.

Files:

- `laravel/app/Services/PageMarkerService.php`
- `laravel/tests/Feature/Workflow/VersionEditorCacheTest.php`

### Publication / comparison editor interaction

- `dev` publish skips legacy re-copy when the draft mirror is already identical.
- `dev` publish ignores editor backup files:
  - `source.original.xhtml`
  - `target.original.xhtml`
- This prevents `dev` publish failures after marker editing in the comparison editor.

Files:

- `laravel/app/Http/Controllers/PublishController.php`
- `laravel/tests/Feature/Workflow/PublicationWorkflowTest.php`

### Shared write permissions for Medite outputs

- Medite entrypoint now prepares shared upload/storage trees as group-writable before Flask/Celery writes comparison outputs.

Files:

- `medite/entrypoint.sh`

### Staging asset sync / deploy hygiene

- VM deploy notes include the mandatory Vite asset sync step.
- Helper script added to sync `laravel/public/build` from the running Laravel container back to the VM checkout.

Files:

- `descr/deployment_notes.md`
- `scripts/sync_vm_vite_assets.sh`

### Laravel upgrade preparation

- Stable namespace values pinned ahead of future Laravel 12/13 work:
  - `SESSION_COOKIE=variance_admin_session`
  - `CACHE_PREFIX=variance_cache_`
  - `REDIS_PREFIX=variance_database_`

Files:

- `laravel/example.env`
- `laravel.env.example`

## Validation Already Covered Locally

Run again before push if new code is added.

```bash
docker compose exec -T laravel php artisan test \
  tests/Feature/Workflow/VersionReaderWorkflowTest.php \
  tests/Feature/Workflow/VersionEditorCacheTest.php \
  tests/Feature/Workflow/PublicationWorkflowTest.php
```

Expected focus:

- Reader auth and cache invalidation
- Editor cache persistence
- `dev` publication with synced legacy drafts
- `dev` publication after comparison-editor backup files exist

## Pre-Deploy Checks Tomorrow

1. Review `git status` and keep the usual local-only folders out of the commit:
   - `demo_pda_partie1/`
   - `protocole/`
   - `variance_data/`
2. Confirm the intended commit includes both `laravel` and `medite` changes.
3. Push to the branch that triggers the stage image build workflow.
4. Wait for image build completion before touching the VM.

## VM Deploy Procedure

On `plett-stage.unil.ch`:

```bash
cd /var/www/variance-input
git fetch origin
git checkout development
git pull --ff-only
```

Set `APP_GIT_SHA` in `laravel.env` to the deployed commit SHA.

Then:

```bash
docker compose -f docker-compose.vm.yml pull laravel laravel-queue medite
docker compose -f docker-compose.vm.yml up -d --force-recreate \
  laravel laravel-queue laravel-scheduler medite variance-proxy
docker compose -f docker-compose.vm.yml exec -T laravel php artisan migrate --force
docker compose -f docker-compose.vm.yml exec -T laravel sh -lc 'php artisan optimize:clear'
./scripts/sync_vm_vite_assets.sh
docker compose -f docker-compose.vm.yml ps
```

## Post-Deploy Verification

Run on the VM:

```bash
curl -I http://127.0.0.1:8081/
curl -I http://127.0.0.1:8081/admin/
curl http://127.0.0.1:8081/health
docker compose -f docker-compose.vm.yml logs --tail=100 laravel laravel-queue laravel-scheduler medite variance-proxy
```

Then smoke-test in the browser:

- open admin
- open a version editor and confirm text + facsimiles load
- reload the editor once to confirm the cached path is fast
- publish a comparison to `dev`
- publish a comparison to `dev` with `Oui, ajouter un marqueur`
- publish to `prod`

## Reader Warm-Up Note

The legacy reader warm-up already completed successfully on staging during this session.

Do **not** rerun it by default tomorrow unless one of these changed:

- the staging storage volume was reset
- reader artifact files were removed
- legacy reader source inputs were changed again

## Success Criteria

The deploy is complete when all of the following are true:

- `/health` is `ok`
- admin JS assets load from `/admin/build/...`
- version editor loads text and facsimiles
- editor reload is fast on the second load
- `dev` publish works after comparison-editor marker changes
- `dev` publish with default-marker insertion works
- `prod` publish still works
- Medite remains healthy
