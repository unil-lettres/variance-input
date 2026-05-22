# Variance-Input Production Cutover Plan

Planning document created on 2026-05-22.

Scope: replace the current legacy production Variance app on `plett`
(`variance.unil.ch`) with the `variance-input` stack.

Status: planning only. No production change has been approved or executed from
this document.

## Goal

Make `variance.unil.ch` serve the integrated `variance-input` stack:

- legacy public site at `/`
- draft/public dev routes where applicable
- Laravel admin at `/admin`
- Laravel health endpoints at `/health` and `/admin/health/report`
- Medite and Laravel queues available for publication workflows

The target business goal is to make Variance operational again for publication
of works by 2026-06-10 at the latest, or earlier if rehearsal and validation are
clean.

## Data Source Position

Current planning decision:

- The current production legacy DB on `plett` contains legacy authors, works,
  versions, comparisons, and chapters. That data must be preserved and remain
  rollback-capable.
- At this stage, the staging/test `variance-input` environment at
  `plt-tst-1.unil.ch` is the source of truth for the new production target,
  because it contains:
  - the legacy media/import state needed by `variance-input`
  - imported versions
  - new creations made in the new admin workflow
- Production cutover should therefore not blindly import the old legacy prod DB
  over the staging-derived `variance-input` database.
- The migration plan needs a reconciliation step:
  - preserve/export the current legacy prod DB for rollback and audit
  - copy/promote the validated staging `variance-input` data into the new prod
    `variance-input` DB container
  - compare legacy prod counts and key identifiers against the staging-derived
    target so no required legacy records are lost
  - decide how to handle any production legacy delta created after the staging
    import

Operational implication: the legacy prod DB is a protected source and rollback
asset; staging `variance-input` is the candidate forward-production data source.

## Staging Source Inventory (`plt-tst-1.unil.ch`)

Collected on 2026-05-22 using read-only SSH commands.

### Host And Deployment

- SSH target used by local config: `plt-tst-1.unil.ch`
- Static hostname: `plett-stage`
- OS: Ubuntu 24.04.4 LTS
- Deployment path: `/var/www/variance-input`
- Compose project: `variance-input`
- Compose file: `/var/www/variance-input/docker-compose.vm.yml`
- Git branch: `development`
- Git revision: `5156f83`
- Latest commit: `Fix empty chapters panel opening`

`/var/www/variance-input` contains untracked deployment/runtime paths:

```text
laravel.env
var/
variance/dev/uploads/uploads
variance/dev/uploads_images/uploads_images
variance/uploads
variance/uploads_images
```

These are expected deployment/runtime artifacts, not source changes to promote
as Git edits.

### Running Staging Services

`variance-input` staging services:

- `laravel`: `unillett/variance-input:laravel-stage-latest`
- `laravel-queue`: `unillett/variance-input:laravel-stage-latest`
- `laravel-scheduler`: `unillett/variance-input:laravel-stage-latest`
- `mariadb`: `mariadb:11.5.2`
- `medite`: `unillett/variance-input:medite-stage-latest`
- `redis`: `redis:7.0`
- `variance-app`: `unillett/variance-input-legacy:stage-latest`
- `variance-web`: `httpd:2.4`
- `variance-proxy`: `nginx:1.27`, published at `127.0.0.1:8081->80`

Planning implication: staging is still using `stage-latest` image tags. For
production, replace these with pinned production tags or digests.

### Staging Database Counts

Staging MariaDB has one application schema:

```text
variance
```

Exact row counts observed:

```text
authors       11
works         21
versions      87
comparisons   60
chapters      813
users         6
permissions   5
works_status  21
failed_jobs   6
jobs          0
```

Comparison with current legacy prod confirms staging has additional
forward-production data:

```text
legacy prod: authors=10 works=18 versions=78 comparisons=57 chapters=813
staging:     authors=11 works=21 versions=87 comparisons=60 chapters=813
```

The `failed_jobs` rows should be reviewed before promotion. They may be harmless
history, but production should not inherit unresolved operational noise unless
there is a reason to keep it.

### Staging Versus Legacy Reconciliation

Read-only metadata comparison on 2026-05-22 showed that staging has the same
legacy record counts as current prod legacy, plus new `variance-input` records.

Staging breakdown by `is_legacy`:

```text
authors      legacy=10  non_legacy=1
works        legacy=18  non_legacy=3
versions     legacy=78  non_legacy=9
comparisons  legacy=57  non_legacy=3
```

This matches current prod legacy counts for the legacy subset:

```text
prod legacy authors      10
prod legacy works        18
prod legacy versions     78
prod legacy comparisons  57
prod legacy chapters     813
staging legacy chapters  813
```

Staging-only author:

```text
edmond_henri_crisinel  Edmond-Henri Crisinel
```

Staging-only works:

```text
alectone            Alectone
la_cousine_bette    La Cousine Bette
melmoth_reconcilie  Melmoth réconcilié
```

Staging-only versions:

```text
alectone:
  1alc  Aux Portes de France (1944)
  2alc  Aux Portes de France (1947)

la_cousine_bette:
  1lcb  Le Constitutionnel (1846)
  2lcb  Chlendowski-Pétion (1847)
  3lcb  Chlendowski-Pétion (1847) + Le Constitutionnel (1846)
  6lcb  Furne (1848)

melmoth_reconcilie:
  1mr   Lequien (1835)
  2mr   Werdet (1836)
  3mr   Furne (1846)
```

Staging-only comparisons:

```text
1alc-2alc-run1
1lcb-2lcb-run1  publication_scope=dev
1mr-2mr-run2
```

Planning implication: promote staging `variance-input` data as the forward
production source, while preserving the current prod legacy database as a
rollback/audit artifact. The current metadata comparison does not show a legacy
count gap in staging, but final rehearsal should still compare stable
identifiers/folders immediately before cutover.

### Staging Storage Footprint

Filesystem headroom:

```text
/      xfs 27G used 12G available 16G use 44%
/var   xfs 90G used 66G available 25G use 74%
```

Key data directories:

```text
/var/www/variance-input/var/dbdata          155M
/var/www/variance-input/var/laravel_storage 2.1G
/var/www/variance-input/var/uploads         20G
/var/www/variance-input/var/uploads_images  17M
/var/www/variance-input/var/uploads_pdf     18M
/var/www/variance-input/var/variance_data   0
```

`du` reported permission warnings for some DB and Laravel private cache
subdirectories, but still produced useful top-level sizes.

File counts:

```text
uploads top-level directories  17
uploads files                  67193
uploads image-like files       66445
uploads JSON files             154
uploads XHTML files            376
uploads_images files           34
uploads_pdf files              42
```

Laravel storage details:

```text
laravel_storage/app/public      1.6G
private/lignes                  20K
private/pagination              112K
private/exports                 0
private/reader_cache            permission-restricted, present
```

### Staging Media Layout

Staging media is local under `/var/www/variance-input/var`, not mounted from
NAS:

```text
findmnt -T /var/www/variance-input/var/uploads -> /var on xfs
```

Legacy-facing paths are symlinked or mounted through compose:

```text
/var/www/variance-input/variance/uploads
  -> /var/www/variance-input/var/uploads

/var/www/variance-input/variance/uploads_images
  -> /var/www/variance-input/var/uploads_images
```

The `variance/dev/uploads` and `variance/dev/uploads_images` directories contain
nested symlinks named `uploads` and `uploads_images` pointing back to the same
`var/` targets. Verify whether this nested layout is intentional before
replicating it on production.

### Staging Backups

Staging has an application-specific DB backup script:

```text
/var/www/variance-input/var/db_backup_daily.sh
```

Crontab:

```text
15 3 * * * /var/www/variance-input/var/db_backup_daily.sh >> /var/www/variance-input/var/log/db_backup_daily.log 2>&1
```

The script:

- sources `laravel.env`
- runs `mariadb-dump` inside the compose `mariadb` service
- uses `--single-transaction`, `--quick`, `--skip-lock-tables`, and `utf8mb4`
- writes compressed dumps under `/var/www/variance-input/var/db_backups`
- checks that the temp dump is non-empty
- runs `gzip -t`
- retains roughly 14 days of `variance_*.sql.gz`

Latest observed backup set includes daily dumps through:

```text
variance_20260522_031501.sql.gz 3.1M
```

This backup pattern is a better model for `variance-input` production than the
legacy prod crontab with inline passwords.

### Staging Local Smoke Checks

Localhost checks through `variance-proxy` at `127.0.0.1:8081`:

- `/health` returned `200` with `{"status":"ok"}`
- `/` returned `200`
- `/admin/` returned `302` to `/admin/login`

Note: the smoke command output included generated `Set-Cookie` headers. These
were transient test responses and are not copied here.

## Hard Constraints

- `plett` hosts multiple applications. Any discovery, deployment, Apache
  reload, Docker operation, disk change, or mount change must be scoped to
  Variance and must not disturb unrelated applications.
- Existing legacy Variance folders under `/var/www/variance` must remain intact
  during first cutover so the legacy app remains rollback-capable.
- NAS media shares must continue to be used for production media.
- Do not commit hostnames, credentials, private mount credentials, or internal
  operations details beyond non-secret paths already known in this document.
- Before execution, create an explicit production request/policy entry. Current
  Variance agent policy is documented for `local` and `test`; production
  cutover needs its own approved request, rollback plan, and backup plan.

## Current NAS Findings On `plett`

The current legacy production media is mounted from CIFS NAS shares.

Observed with `df -hT`:

```text
//nasdcsr.unil.ch/RECHERCHE/PLTF/Lettres/NUCL/infranuc/variance/D2C/prod/uploads
  -> /var/www/variance/uploads

//nasdcsr.unil.ch/RECHERCHE/PLTF/Lettres/NUCL/infranuc/variance/D2C/prod/uploads_images
  -> /var/www/variance/uploads_images

//nasdcsr.unil.ch/RECHERCHE/PLTF/Lettres/NUCL/infranuc/variance/D2C/dev/uploads
  -> /var/www/variance/dev/uploads

//nasdcsr.unil.ch/RECHERCHE/PLTF/Lettres/NUCL/infranuc/variance/D2C/dev/uploads_images
  -> /var/www/variance/dev/uploads_images
```

Disk headroom on the NAS share was approximately:

```text
Size: 200G
Used: 36G
Available: 165G
Use: 18%
```

Path-specific checks confirmed:

```text
/var/www/variance/uploads        deployer:www-data 775
/var/www/variance/uploads_images deployer:www-data 775
```

Persistent mount definitions are in `/etc/fstab`:

```text
//nasdcsr.unil.ch/.../variance/D2C/prod/uploads        /var/www/variance/uploads
//nasdcsr.unil.ch/.../variance/D2C/prod/uploads_images /var/www/variance/uploads_images
//nasdcsr.unil.ch/.../variance/D2C/dev/uploads         /var/www/variance/dev/uploads
//nasdcsr.unil.ch/.../variance/D2C/dev/uploads_images  /var/www/variance/dev/uploads_images
```

Options observed:

```text
cifs credentials=/root/.smbcredentials,uid=deployer,gid=www-data,file_mode=0775,dir_mode=0775,noperm,noserverino 0 0
```

Credentials are externalized in `/root/.smbcredentials`; do not display or copy
that file into project documentation.

## NAS Design For `variance-input`

Do not reuse `/var/www/variance/...` as the direct host paths for
`variance-input` containers. Those paths belong to the legacy app and are part
of the rollback surface.

Preferred design: add parallel mount targets for `variance-input`, pointing to
the same NAS shares, for example:

```text
/var/www/variance-input/var/uploads
/var/www/variance-input/var/uploads_images
/var/www/variance-input/var/dev/uploads
/var/www/variance-input/var/dev/uploads_images
```

Use the same effective mount ownership and modes:

```text
uid=deployer,gid=www-data,file_mode=0775,dir_mode=0775,noperm,noserverino
```

The current VM compose file expects these host-side paths:

```text
./var/uploads
./var/uploads_images
./var/uploads_pdf
./var/laravel_storage
./var/variance_data
```

For production, map that expectation to the chosen deploy root, likely:

```text
/var/www/variance-input/var/uploads
/var/www/variance-input/var/uploads_images
/var/www/variance-input/var/uploads_pdf
/var/www/variance-input/var/laravel_storage
/var/www/variance-input/var/variance_data
```

Open point: current legacy `df` output shows CIFS mounts for `uploads` and
`uploads_images`, but not a separate top-level `uploads/pdf` mount. Follow-up
preflight confirmed that `/var/www/variance/uploads/pdf` is inside the
`/var/www/variance/uploads` CIFS mount.

## Proposed Production Data Layout

Target deploy root:

```text
/var/www/variance-input
```

Target data root:

```text
/var/www/variance-input/var
```

Proposed paths:

```text
/var/www/variance-input/var/dbdata
/var/www/variance-input/var/laravel_storage
/var/www/variance-input/var/uploads
/var/www/variance-input/var/uploads_images
/var/www/variance-input/var/uploads_pdf
/var/www/variance-input/var/variance_data
/var/www/variance-input/var/db_backups
/var/www/variance-input/var/log
```

Persistence model:

- `dbdata`: local durable storage for the new production MariaDB container.
- `laravel_storage`: local durable Laravel storage, including private sidecars,
  reader caches, exports, queue/scheduler health files, and public storage.
- `uploads`: NAS-backed production media tree.
- `uploads_images`: NAS-backed production cover image tree.
- `uploads_pdf`: app-facing PDF path; chosen production layout is a symlink or
  bind mount to `uploads/pdf` inside the NAS-backed `uploads` tree.
- `variance_data`: local durable Medite/private runtime data.
- `db_backups`: local durable compressed DB backups for the new DB container.
- `log`: local backup logs and operational logs owned by the deployment user.

PDF layout decision:

- Staging uses a separate `var/uploads_pdf` directory.
- Legacy prod stores PDFs under `uploads/pdf`, inside the `uploads` CIFS share.
- Current compose mounts `./var/uploads_pdf` into `/var/www/variance/uploads/pdf`
  for Laravel/legacy containers.

Chosen production option:

```text
/var/www/variance-input/var/uploads_pdf -> /var/www/variance-input/var/uploads/pdf
```

Use a symlink or bind mount so the app keeps the compose-facing
`./var/uploads_pdf` path while the actual files live inside the NAS-backed
`uploads` tree, matching legacy production layout. Validate this carefully
before execution because replacing a real directory with a symlink is a write
operation and needs a backup/rollback step.

Rejected alternative for first production cutover:

```text
/var/www/variance-input/var/uploads_pdf
```

Keep PDFs separate, as staging does. This is simpler for the current compose
file but diverges from the existing legacy NAS layout and may require explicit
NAS persistence for that path.

## Risk Register

| Risk | Impact | Mitigation |
| --- | --- | --- |
| `plett` hosts multiple apps | A broad Docker, Apache, mount, or disk action could affect unrelated services. | Scope every command to `variance-input` or the Variance vhost; avoid host-wide Docker operations; run Apache config tests before reload. |
| Prod legacy tree is dirty | Rollback cannot rely on Git alone. | Snapshot current `/var/www/variance` config files and preserve the dirty diffs before cutover. |
| Current prod legacy DB and new prod DB have different roles | Accidental restore from legacy prod over staging-derived data would lose new work. | Treat legacy prod DB as rollback/audit only; restore staging `variance-input` dump into new prod DB. |
| Staging media is local but prod media should be NAS-backed | Permissions or path behavior can differ after sync. | Mount parallel NAS paths under `/var/www/variance-input/var`, verify ownership/modes, run write tests in a controlled preflight directory, and recount files. |
| PDF layout changes from staging | Symlink/bind for `var/uploads_pdf` could break paths if handled incorrectly. | Use chosen layout `var/uploads_pdf -> var/uploads/pdf`, snapshot any existing directory first, and validate PDF URLs before cutover. |
| Production image tags are not final | `stage-latest` would make deployment non-reproducible. | Publish and record prod tags or digests before deployment; update `docker-compose.prod.yml`. |
| Staging DB has `failed_jobs=6` | Operational noise may be promoted to production. | Review or clear failed job rows deliberately before final dump. |
| NAS credentials are shared host-level config | Mount changes may expose or break shared credentials. | Reuse existing `/root/.smbcredentials` without copying secrets; do not document secret values. |
| First public requests may rebuild reader/cache artifacts | Slow first loads or timeouts for large works. | Warm representative reader artifacts after deployment and before public handoff. |
| Apache cutover mistake | Public site outage. | Change only `variance.unil.ch` proxy target, run `apache2ctl configtest`, keep legacy app running at `127.0.0.1:8282`, and rollback by restoring the previous proxy target. |

## Approval Checklist

Before any production write or cutover:

- [ ] Production request YAML is approved and loaded.
- [ ] Maintenance/freeze window is agreed with Maxime/Joël/editors.
- [ ] Staging `variance-input` is approved as forward production source.
- [ ] Current prod legacy DB dump is fresh, non-empty, and retained for rollback.
- [ ] Fresh staging `variance-input` DB dump is created and verified with `gzip -t`.
- [ ] Staging versus prod legacy reconciliation has been repeated immediately before promotion.
- [ ] Production image tags/digests are finalized and recorded.
- [ ] `docker-compose.prod.yml` is updated with those image tags/digests.
- [ ] `laravel.env` and `variance/.env` are created on prod with real secrets and not committed.
- [ ] Parallel NAS mounts under `/var/www/variance-input/var` are approved.
- [ ] Chosen PDF layout (`var/uploads_pdf -> var/uploads/pdf`) is approved.
- [ ] Media/storage sync dry-run output is reviewed.
- [ ] Rollback owner is named.
- [ ] Apache reload is approved.
- [ ] Communication channel is open during cutover.

## Validation Corpus

Fill exact URLs/IDs during rehearsal. The corpus should include both legacy
records and new `variance-input` creations.

| Case | Candidate | Purpose | Exact URL/ID |
| --- | --- | --- | --- |
| New work | `Alectone` | Validate new author/work/version/comparison flow from staging. | TBD |
| New comparison | `1alc-2alc-run1` | Validate non-legacy comparison output and Medite artifacts. | TBD |
| New dev publication | `1lcb-2lcb-run1` (`publication_scope=dev`) | Validate draft/dev publication handling. | TBD |
| New work | `Melmoth réconcilié` | Validate another non-legacy imported/created work. | TBD |
| Legacy public comparison | Balzac comparison, e.g. `1vndtt-2vndtt` or another agreed page | Validate legacy public route compatibility. | TBD |
| PDF link | A known notice PDF from staging/prod | Validate `uploads_pdf`/`uploads/pdf` layout. | TBD |
| Cover image | A known `/uploads_images/...` cover | Validate NAS-backed cover image path. | TBD |
| Facsimile-heavy case | A work/comparison with many `img_*` files | Validate facsimile access and manifests. | TBD |
| Large reader case | Largest known reader/cache case | Validate first-load or warmed reader behavior. | TBD |
| Admin health | `/admin/health/report` | Validate queue, scheduler, Medite, DB, disk checks. | TBD |

## Proposed Production Compose Design

Create a production compose file derived from `docker-compose.vm.yml`.

Recommended filename:

```text
docker-compose.prod.yml
```

Draft artifact now exists in the repository:

```text
docker-compose.prod.yml
```

It is not yet executable for production. It still contains placeholder image
references such as:

```text
unillett/variance-input:laravel-REPLACE_WITH_RELEASE_TAG
unillett/variance-input:medite-REPLACE_WITH_RELEASE_TAG
unillett/variance-input-legacy:prod-REPLACE_WITH_GIT_SHA
```

Local `docker compose -f docker-compose.prod.yml config --quiet` reached compose
validation but stopped because the real runtime file `laravel.env` is not
present in the local checkout. That is expected; the real env file must exist
only on the deployment host and must not be committed.

Project name:

```text
variance-input
```

Do not use project name `variance`, because the legacy production app already
uses the `variance` Compose project and container/volume namespace.

Services:

- `mariadb`
- `redis`
- `laravel`
- `laravel-queue`
- `laravel-scheduler`
- `laravel-assets`
- `medite`
- `variance-web`
- `variance-app`
- `variance-proxy`

Image policy:

- Replace all `stage-latest` tags with pinned production tags or digests.
- Candidate production image references must be recorded before deployment:
  - Laravel image
  - Medite image
  - integrated legacy image
- Base images should remain pinned by major/minor line as currently:
  - `mariadb:11.5.2`
  - `redis:7.0`
  - `nginx:1.27`
  - `httpd:2.4`

Port:

```text
127.0.0.1:8081:80
```

This was free on `plett` during preflight and matches staging.

Volumes should follow the target production data layout:

```text
./var/dbdata:/var/lib/mysql
./var/laravel_storage:/var/www/html/storage
./var/variance_data:/var/www/html/storage/app/private/variance_data
./var/uploads:/var/www/html/public/uploads
./var/uploads_images:/var/www/html/public/uploads_images
./var/uploads:/var/www/variance/uploads
./var/uploads_images:/var/www/variance/uploads_images
./var/uploads_pdf:/var/www/variance/uploads/pdf
```

For `medite`:

```text
./var/uploads:/app/uploads
./var/variance_data:/app/variance_data
./var/laravel_storage/app/public:/app/storage_public
```

For `variance-web` and `variance-app`:

```text
./variance:/var/www
./var/uploads:/var/www/uploads
./var/uploads_images:/var/www/uploads_images
./var/uploads_pdf:/var/www/uploads/pdf
```

Keep `laravel-assets` and the `vite_build` volume so `/admin/build/...` is
served from the image-matched asset build.

## Production Image Strategy

Current workflow inventory:

- `.github/workflows/docker-stage.yml`
  - runs on pushes to `development`
  - builds `laravel` and `medite`
  - uses the shared `unil-lettres/actions/docker-build` and
    `docker-merge-stage` actions
  - publishes stage tags for the repository configured by
    `vars.DOCKERHUB_REPOSITORY`
- `.github/workflows/docker-prod.yml`
  - runs on pushes to `main` and all Git tags
  - builds `laravel` and `medite`
  - uses `docker-merge-prod`
  - does not build the integrated legacy image
  - checkout refs were corrected on 2026-05-22 from hard-coded `main` to
    `${{ github.ref }}`, so tag-triggered builds use the tagged source rather
    than the current `main` head
- `.github/workflows/docker-legacy-stage.yml`
  - runs on pushes to `development` touching `variance/**` or the workflow
  - builds `unillett/variance-input-legacy`
  - publishes:
    - `unillett/variance-input-legacy:stage-latest`
    - `unillett/variance-input-legacy:stage-${github.sha}`
- `.github/workflows/docker-legacy-prod.yml`
  - draft workflow added for production integrated legacy builds
  - runs on pushes to `main` and Git tags touching `variance/**` or the workflow
  - publishes:
    - `unillett/variance-input-legacy:prod-latest`
    - `unillett/variance-input-legacy:prod-${github.sha}`

Current gap:

- Laravel and Medite have a production workflow path through `docker-prod.yml`.
- The integrated legacy runtime now has a draft production workflow, but it
  still needs to be committed, merged, and run from the approved release source
  before its production tag/digest can be used.
- `docker-compose.prod.yml` cannot be finalized until the exact production
  tags/digests are recorded.

Docker Hub tag inventory checked on 2026-05-22:

```text
unillett/variance-input
  laravel-stage-latest
    sha256:2b40732abbaf34e4b90a70cf98ea5c1a9df86cf90e4006ce2b3029c935cb29e7
  laravel-stage-5156f83-20260521150409
    sha256:2b40732abbaf34e4b90a70cf98ea5c1a9df86cf90e4006ce2b3029c935cb29e7
  medite-stage-latest
    sha256:19a72fc8f9e994648002708cfffa1f56bc41e8a6216eaaa09d85acab6f63518d
  medite-stage-5156f83-20260521150412
    sha256:19a72fc8f9e994648002708cfffa1f56bc41e8a6216eaaa09d85acab6f63518d

unillett/variance-input-legacy
  stage-latest
    sha256:55feb4b83aa7acbad8c0a89201af6e9f080fa8e7e87790923166b13d1145a4af
```

No production Laravel, Medite, or integrated legacy tags were observed in the
Docker Hub tag inventory. The final production release therefore still needs to
publish prod tags before `docker-compose.prod.yml` can be made executable for
cutover.

Shared `docker-merge-prod` behavior checked on 2026-05-22 from
`unil-lettres/actions/main/docker-merge-prod/action.yml`:

- on a branch push, the published tag is `<service>-latest`
- on a Git tag push, the published tag is `<service>-<latest_git_tag>`
- with the current workflow matrix, expected production tags are therefore:

```text
main push:
  unillett/variance-input:laravel-latest
  unillett/variance-input:medite-latest

tag push, for example v2026.06.01:
  unillett/variance-input:laravel-v2026.06.01
  unillett/variance-input:medite-v2026.06.01
```

`docker-compose.prod.yml` placeholders were aligned with this convention on
2026-05-22. They still must be replaced with exact production tags or digests
before execution.

Recommended release convention:

Use immutable production tags plus record digests:

```text
unillett/variance-input:laravel-<release-tag>
unillett/variance-input:medite-<release-tag>
unillett/variance-input-legacy:prod-<commit>
```

The Laravel and Medite tags should come from the actual `docker-prod.yml` run.
The integrated legacy tag currently comes from the new draft legacy workflow and
uses `prod-${github.sha}`.

Workflow follow-up:

- Commit and merge `.github/workflows/docker-legacy-prod.yml`.
- Let `docker-prod.yml` and `docker-legacy-prod.yml` run from the approved
  release source.
- Use `prod-latest` only for discovery/operator convenience.
- Deploy Laravel and Medite by release tag or digest, for example
  `laravel-v2026.06.01` and `medite-v2026.06.01`.
- Deploy integrated legacy by immutable `prod-${github.sha}` tag or digest.
- Prefer deploying by immutable tags or digests, not by `latest` or
  `prod-latest`.

Before cutover, record:

```text
Laravel image tag:
Laravel image digest:
Medite image tag:
Medite image digest:
Integrated legacy image tag:
Integrated legacy image digest:
Source Git commit:
GitHub Actions run IDs:
```

Then update `docker-compose.prod.yml` to replace all image placeholders.

CI validation follow-up:

- `.github/workflows/dependency-checks.yml` now includes
  `docker-compose.prod.yml`, `laravel.prod.env.example`, and
  `variance/prod.env.example` in its trigger paths.
- The compose-config job now validates `docker-compose.prod.yml` using copied
  example env files.
- Local validation with copied example env files passed on 2026-05-22:

```bash
cp laravel.prod.env.example laravel.env
docker compose -f docker-compose.prod.yml config --quiet
rm laravel.env
```

## Production Environment Checklist

Use a production-specific `laravel.env`. Do not copy staging values blindly.

Draft non-secret examples now exist:

```text
laravel.prod.env.example
variance/prod.env.example
```

Deployment convention:

- copy `laravel.prod.env.example` to `laravel.env` on the production host and
  fill real secrets there
- copy `variance/prod.env.example` to `variance/.env` on the production host and
  fill real secrets there
- do not commit populated env files

Required values:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://variance.unil.ch
ADMIN_BASE_PATH=/admin
APP_GIT_SHA=<deployed commit sha>
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
```

Stable Laravel namespace values:

```text
SESSION_COOKIE=variance_admin_session
CACHE_PREFIX=variance_cache_
REDIS_PREFIX=variance_database_
```

Session and proxy-related values:

```text
SESSION_DOMAIN=variance.unil.ch
SESSION_PATH=/
ASSET_URL=
```

Database values:

```text
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=<prod variance-input db name>
DB_USERNAME=<prod variance-input db user>
DB_PASSWORD=<secret>
```

Queue/cache:

```text
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Operational values:

```text
LOG_CHANNEL=stack
LOG_LEVEL=warning
TXT_IMPORT_MODE=<confirm from staging/current policy>
```

Health/backup values to review:

```text
DB_BACKUP_TIME=03:15
DB_BACKUP_RETENTION_DAYS=14
HEALTHCHECK_DISK_WARN_GB=<confirm>
HEALTHCHECK_DISK_CRIT_GB=<confirm>
```

Mail settings: confirm whether production admin needs outgoing email. If not,
set a safe non-sending transport or document that mail is intentionally disabled.

Legacy `variance/.env`: create a production-specific legacy env file for the
integrated legacy runtime. Preserve the current legacy prod env separately for
rollback and do not reuse secrets blindly.

## Data Promotion Procedure Draft

This is a draft. Do not execute without an approved production request,
rollback plan, and maintenance window.

### Inputs

- Fresh staging DB dump from `plt-tst-1.unil.ch`.
- Fresh staging media/storage snapshot:
  - `/var/www/variance-input/var/uploads`
  - `/var/www/variance-input/var/uploads_images`
  - `/var/www/variance-input/var/uploads_pdf`
  - `/var/www/variance-input/var/laravel_storage`
  - `/var/www/variance-input/var/variance_data`
- Fresh prod legacy DB dump from `plett` for rollback/audit.
- Snapshot of current prod legacy Apache and Compose config.

### DB Promotion

1. On staging, create or select a fresh verified DB dump.
2. Transfer the dump to the new prod `variance-input` deploy area.
3. Start the new prod MariaDB container with empty persistent `dbdata`.
4. Restore the staging dump into the new prod DB container.
5. Run Laravel migrations if the production code requires migrations after the
   dump was created.
6. Run exact row-count checks:
   - `authors`
   - `works`
   - `versions`
   - `comparisons`
   - `chapters`
   - `users`
   - `permissions`
   - `works_status`
   - `jobs`
   - `failed_jobs`
7. Confirm staging-only records are present:
   - `Alectone`
   - `La Cousine Bette`
   - `Melmoth réconcilié`
   - `1alc-2alc-run1`
   - `1lcb-2lcb-run1`
   - `1mr-2mr-run2`

### Media And Storage Promotion

1. Prepare parallel `variance-input` target paths on `plett`.
2. Mount NAS-backed `uploads` and `uploads_images` at the new target paths.
3. Sync staging media into the new production target paths.
4. Sync Laravel storage into the new production target path.
5. Preserve ownership/group writability:
   - app-readable and writable by the container user/group
   - host group compatible with `www-data` where needed
6. Recount files after sync:
   - total upload files
   - image-like upload files
   - JSON manifests
   - XHTML files
   - cover images
   - PDFs
7. Validate that legacy-facing paths resolve:
   - `/var/www/variance-input/variance/uploads`
   - `/var/www/variance-input/variance/uploads_images`
   - `/var/www/variance-input/variance/dev/uploads`
   - `/var/www/variance-input/variance/dev/uploads_images`

### Post-Promotion Sanity

Before Apache cutover:

1. Start `variance-input` on `127.0.0.1:8081`.
2. Run:
   - `docker compose -f docker-compose.prod.yml ps`
   - `curl http://127.0.0.1:8081/health`
   - `curl -I http://127.0.0.1:8081/`
   - `curl -I http://127.0.0.1:8081/admin/`
3. Check `/admin/health/report` through authenticated browser access.
4. Validate representative public pages and media.

## Production Cutover Checklist

This checklist is for the approved cutover window only.

Preconditions:

- Final staging data source frozen or approved for promotion.
- Final prod legacy editor freeze communicated.
- Fresh prod legacy DB dump verified.
- Fresh staging `variance-input` DB dump verified.
- Media/storage sync completed and file counts match.
- Prod `variance-input` is healthy on `127.0.0.1:8081`.
- Apache rollback config is available.
- Rollback owner and communication channel are known.

Cutover steps:

1. Save current Apache variance vhost files:
   - `/etc/apache2/sites-enabled/variance.conf`
   - `/etc/apache2/sites-enabled/variance-le-ssl.conf`
2. Confirm current legacy target:
   - `ProxyPass / http://127.0.0.1:8282/`
3. Change only the `variance.unil.ch` HTTPS proxy target to:
   - `ProxyPass / http://127.0.0.1:8081/`
   - `ProxyPassReverse / http://127.0.0.1:8081/`
4. Keep HTTP redirect behavior unchanged.
5. Run `apache2ctl configtest`.
6. Reload Apache with a graceful reload.
7. Smoke test:
   - `https://variance.unil.ch/`
   - `https://variance.unil.ch/admin/`
   - `https://variance.unil.ch/health`
8. Browser validate representative public pages, PDFs, cover images, and
   facsimile-heavy comparisons.
9. Validate `/admin/health/report`.
10. Monitor:
   - Apache logs
   - `variance-input` container logs
   - queue heartbeat
   - scheduler heartbeat
   - disk usage

Do not stop or remove the legacy `variance` Compose project during first
cutover. It is the immediate rollback target.

## Rollback Checklist

Rollback target: current legacy app at `127.0.0.1:8282`.

Rollback steps:

1. Restore the previous `variance-le-ssl.conf`, or change proxy target back to:
   - `ProxyPass / http://127.0.0.1:8282/`
   - `ProxyPassReverse / http://127.0.0.1:8282/`
2. Run `apache2ctl configtest`.
3. Gracefully reload Apache.
4. Verify:
   - `https://variance.unil.ch/`
   - a known legacy comparison page
   - a known PDF link
   - a known cover/facsimile image
5. Keep `variance-input` containers and data intact for post-rollback analysis.
6. Do not delete new `variance-input` DB/media/storage until the cause is
   understood and a new plan is approved.

Rollback data preservation:

- Current prod legacy DB volume:
  - `variance_mysql-data`
- Current prod legacy dev DB volume:
  - `variance_mysql-data-dev`
- Current prod legacy deploy tree:
  - `/var/www/variance`
- Current prod legacy media paths:
  - `/var/www/variance/uploads`
  - `/var/www/variance/uploads_images`
  - `/var/www/variance/dev/uploads`
  - `/var/www/variance/dev/uploads_images`

## Final Rehearsal Checks

Run these shortly before cutover:

- Re-run prod legacy counts.
- Re-run staging source counts.
- Re-run staging-only metadata check.
- Re-run media file counts after final sync.
- Verify prod `variance-input` DB restore counts.
- Verify prod `variance-input` `/health`.
- Verify prod `variance-input` queue and scheduler health.
- Confirm no unrelated Docker project or Apache vhost changed.

## Draft Command Runbook

Status: draft only. Do not execute until the production request, image tags,
NAS/PDF layout, rollback package, and maintenance window are approved.

Commands are intentionally explicit and scoped. Review every command against the
current host state before use.

### Local Compose Validation With Example Envs

Run locally only to validate the draft compose structure. Do not commit the
generated runtime env files.

```bash
cp laravel.prod.env.example laravel.env
docker compose -f docker-compose.prod.yml config
rm laravel.env
```

Expected caveat: image references still contain placeholder tags until
production tags/digests are decided.

### Production Setup Skeleton On `plett`

Draft only. Requires writes and therefore explicit approval before execution.

```bash
cd /var/www
mkdir -p variance-input
cd /var/www/variance-input
```

Fetch code at the approved release commit:

```bash
git clone <approved-repository-url> .
git checkout <approved-release-commit>
```

Create local data paths:

```bash
mkdir -p var/dbdata
mkdir -p var/laravel_storage
mkdir -p var/variance_data
mkdir -p var/db_backups
mkdir -p var/log
mkdir -p var/uploads
mkdir -p var/uploads_images
```

Prepare env files from examples:

```bash
cp laravel.prod.env.example laravel.env
cp variance/prod.env.example variance/.env
```

Then edit only on the host, filling real secrets and final release metadata.
Do not copy secrets back to Git.

### NAS Mount Draft

Draft only. Requires root-level mount configuration and should be performed in a
maintenance/rehearsal window.

Add parallel mounts for `variance-input`; do not alter existing legacy
`/var/www/variance/...` mounts.

Proposed targets:

```text
/var/www/variance-input/var/uploads
/var/www/variance-input/var/uploads_images
```

Use the same CIFS options as legacy prod:

```text
credentials=/root/.smbcredentials,uid=deployer,gid=www-data,file_mode=0775,dir_mode=0775,noperm,noserverino
```

After mount setup, validate:

```bash
findmnt /var/www/variance-input/var/uploads
findmnt /var/www/variance-input/var/uploads_images
stat -c '%U:%G %a %n' /var/www/variance-input/var/uploads /var/www/variance-input/var/uploads_images
df -hT /var/www/variance-input/var/uploads /var/www/variance-input/var/uploads_images
```

### PDF Layout Draft

Preferred draft decision: keep production PDFs inside the NAS-backed uploads
tree while preserving the app-facing `./var/uploads_pdf` path.

Draft only:

```bash
mkdir -p /var/www/variance-input/var/uploads/pdf
ln -sfn /var/www/variance-input/var/uploads/pdf /var/www/variance-input/var/uploads_pdf
```

Before executing this on a host where `var/uploads_pdf` already exists as a real
directory, snapshot it first and confirm it is safe to replace with a symlink.

Validation:

```bash
readlink -f /var/www/variance-input/var/uploads_pdf
stat -c '%U:%G %a %n' /var/www/variance-input/var/uploads/pdf /var/www/variance-input/var/uploads_pdf
```

Alternative: use a bind mount instead of a symlink if the deployment policy
prefers mount-table explicitness.

### Staging DB Export Draft

Run on `plt-tst-1.unil.ch`. Prefer a fresh dump over reusing an older scheduled
backup for final cutover.

```bash
cd /var/www/variance-input
set -a
. ./laravel.env
set +a
docker compose -f docker-compose.vm.yml exec -T -e MYSQL_PWD="$DB_PASSWORD" mariadb \
  mariadb-dump \
  --single-transaction \
  --quick \
  --skip-lock-tables \
  --default-character-set=utf8mb4 \
  --user="$DB_USERNAME" \
  "$DB_DATABASE" \
  | gzip -9 > "var/db_backups/variance_cutover_$(date +%Y%m%d_%H%M%S).sql.gz"
gzip -t var/db_backups/variance_cutover_*.sql.gz
ls -lh var/db_backups/variance_cutover_*.sql.gz
```

For final execution, avoid globs in commands that could match multiple files.
Record the exact dump filename.

### Transfer Draft

From a trusted operator workstation or from staging, transfer the exact dump to
`plett`.

```bash
scp /var/www/variance-input/var/db_backups/<exact-staging-dump>.sql.gz \
  plett:/var/www/variance-input/var/db_backups/
```

For media/storage promotion, prefer `rsync` with dry-run first:

```bash
rsync -aH --numeric-ids --dry-run \
  /var/www/variance-input/var/uploads/ \
  plett:/var/www/variance-input/var/uploads/

rsync -aH --numeric-ids --dry-run \
  /var/www/variance-input/var/uploads_images/ \
  plett:/var/www/variance-input/var/uploads_images/

rsync -aH --numeric-ids --dry-run \
  /var/www/variance-input/var/uploads_pdf/ \
  plett:/var/www/variance-input/var/uploads_pdf/

rsync -aH --numeric-ids --dry-run \
  /var/www/variance-input/var/laravel_storage/ \
  plett:/var/www/variance-input/var/laravel_storage/

rsync -aH --numeric-ids --dry-run \
  /var/www/variance-input/var/variance_data/ \
  plett:/var/www/variance-input/var/variance_data/
```

Only remove `--dry-run` after the dry-run output has been reviewed.

### Prod DB Restore Draft

Run on `plett`, after the new `variance-input` DB container is created and
healthy, and before Apache cutover.

```bash
cd /var/www/variance-input
docker compose -f docker-compose.prod.yml ps mariadb
gzip -t var/db_backups/<exact-staging-dump>.sql.gz
```

Restore into the new prod DB container:

```bash
set -a
. ./laravel.env
set +a
gzip -dc "var/db_backups/<exact-staging-dump>.sql.gz" | \
  docker compose -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$DB_PASSWORD" mariadb \
    mariadb \
    --user="$DB_USERNAME" \
    "$DB_DATABASE"
```

If the target database is not empty, stop and decide whether to recreate it from
scratch or apply a controlled restore procedure. Do not overwrite data blindly.

### Prod Post-Restore Counts Draft

Run on `plett`.

```bash
cd /var/www/variance-input
set -a
. ./laravel.env
set +a
docker compose -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$DB_PASSWORD" mariadb \
  mariadb --user="$DB_USERNAME" --batch --skip-column-names "$DB_DATABASE" \
  -e "SELECT 'authors', COUNT(*) FROM authors
      UNION ALL SELECT 'works', COUNT(*) FROM works
      UNION ALL SELECT 'versions', COUNT(*) FROM versions
      UNION ALL SELECT 'comparisons', COUNT(*) FROM comparisons
      UNION ALL SELECT 'chapters', COUNT(*) FROM chapters
      UNION ALL SELECT 'users', COUNT(*) FROM users
      UNION ALL SELECT 'permissions', COUNT(*) FROM permissions
      UNION ALL SELECT 'jobs', COUNT(*) FROM jobs
      UNION ALL SELECT 'failed_jobs', COUNT(*) FROM failed_jobs;"
```

Expected baseline from staging on 2026-05-22:

```text
authors       11
works         21
versions      87
comparisons   60
chapters      813
users         6
permissions   5
jobs          0
failed_jobs   6
```

Review whether `failed_jobs` should be cleared before production cutover.

### Prod Media Count Verification Draft

Run on `plett` after sync:

```bash
find /var/www/variance-input/var/uploads -type f | wc -l
find /var/www/variance-input/var/uploads \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' -o -name '*.tif' -o -name '*.tiff' \) | wc -l
find /var/www/variance-input/var/uploads -name '*.json' | wc -l
find /var/www/variance-input/var/uploads -name '*.xhtml' | wc -l
find /var/www/variance-input/var/uploads_images -type f | wc -l
find /var/www/variance-input/var/uploads_pdf -type f | wc -l
du -sh /var/www/variance-input/var/uploads /var/www/variance-input/var/uploads_images /var/www/variance-input/var/uploads_pdf /var/www/variance-input/var/laravel_storage
```

Expected staging baseline on 2026-05-22:

```text
uploads files        67193
image-like files     66445
JSON files             154
XHTML files            376
uploads_images files    34
uploads_pdf files       42
uploads size           20G
laravel_storage       2.1G
```

### Prod Compose And Local Smoke Draft

Run on `plett` only after final env files and image references are in place:

```bash
cd /var/www/variance-input
docker compose -f docker-compose.prod.yml config
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml run --rm laravel-assets
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps
```

Local smoke before Apache cutover:

```bash
curl -sS -o /dev/null -D - -w 'code=%{http_code} time=%{time_total}\n' --max-time 10 http://127.0.0.1:8081/health
curl -sS -o /dev/null -D - -w 'code=%{http_code} time=%{time_total}\n' --max-time 10 http://127.0.0.1:8081/
curl -sS -o /dev/null -D - -w 'code=%{http_code} time=%{time_total}\n' --max-time 10 http://127.0.0.1:8081/admin/
```

Expected:

- `/health`: `200`
- `/`: `200`
- `/admin/`: `302` to login when unauthenticated

## Read-Only Preflight Findings On `plett`

Collected on 2026-05-22 using read-only SSH commands as `deployer`.

### Host

- Hostname: `plett`
- OS: Ubuntu 24.04.4 LTS
- Virtualization: VMware
- Apache service: `apache2`
- Apache state: active since 2026-04-23 10:26:48 CEST
- Apache config test: `Syntax OK`

### Multi-App Inventory

`plett` is a shared host. Apache currently serves multiple name-based vhosts:

- `plett.unil.ch`
- `ajmc.unil.ch`
- `digital-lyric.unil.ch`
- `efle-verbes.unil.ch`
- `florale.unil.ch`
- `grimm.unil.ch`
- `ncd17.unil.ch`
- `revuesculturelles.unil.ch`
- `revuesculturelles.ch`
- `variance.unil.ch`

Running Docker Compose projects:

- `ajmc`
- `diglyr`
- `efleverbes`
- `florale`
- `grimm`
- `ncd17`
- `revuesculturelles`
- `variance`

Operational implication: any Docker command during execution must be scoped by
project/path. Do not use host-wide prune, restart, or broad container commands.

### Current Variance Routing

Apache vhost files:

- HTTP: `/etc/apache2/sites-enabled/variance.conf`
- HTTPS: `/etc/apache2/sites-enabled/variance-le-ssl.conf`

Current HTTP vhost redirects `variance.unil.ch` to HTTPS.

Current HTTPS vhost:

- `ServerName variance.unil.ch`
- `ServerAlias www.variance.unil.ch`
- `DocumentRoot /var/www/variance`
- TLS certificate:
  `/etc/letsencrypt/live/plett.unil.ch-0001/fullchain.pem`
- TLS key:
  `/etc/letsencrypt/live/plett.unil.ch-0001/privkey.pem`
- `ProxyPass / http://127.0.0.1:8282/`
- `ProxyPassReverse / http://127.0.0.1:8282/`
- `ProxyPreserveHost On`

Apache modules needed for this style of cutover are already enabled, including:

- `proxy_module`
- `proxy_http_module`
- `headers_module`
- `rewrite_module`
- `ssl_module`

Cutover is therefore likely a narrow `variance.unil.ch` vhost proxy-target
change, not a DNS change, assuming production validation accepts the
`variance-input` localhost target.

### Localhost Ports

Observed listener ownership:

- Apache: public `:80` and `:443`
- Docker-published localhost ports include:
  - `127.0.0.1:8000`
  - `127.0.0.1:8080`
  - `127.0.0.1:8282`
  - `127.0.0.1:8283`
  - `127.0.0.1:8484`
  - `127.0.0.1:8787`
  - `127.0.0.1:9010`
  - `127.0.0.1:4200`
  - MySQL ports `3303`, `3304`, `3305`, `3307`, `3308`

The current legacy Variance public container is on `127.0.0.1:8282`.

The current `docker-compose.vm.yml` in this repository publishes
`variance-input` on `127.0.0.1:8081:80`, and `8081` was not observed in the
listener list during this preflight.

### Current Legacy Variance Compose

Current project path:

- `/var/www/variance`

Current Compose files:

- `/var/www/variance/docker-compose.yml`
- `/var/www/variance/docker-compose.override.yml`

Current containers:

- `variance-web`: `httpd:2.4`, published at `127.0.0.1:8282->80`
- `variance-app`: `unillett/variance@sha256:8bc41e553788a3da25bfd324e3379eb23a06e106ed1e99792738685f41399ffb`
- `variance-mysql-prod`: `mysql:8`, published at `127.0.0.1:3305->3306`
- `variance-mysql-dev`: `mysql:8`, published at `127.0.0.1:3304->3306`

Current Docker volumes:

- `variance_mysql-data`
- `variance_mysql-data-dev`

The legacy MySQL volumes are local Docker volumes under `/var/lib/docker`.

### Current Legacy Git State

`/var/www/variance` is on branch `master`.

Recent Git commit:

```text
d521d9a 2024-07-09 10:24:53 +0200 Update page direction scientifique
```

The working tree is dirty. Important deployment-related local changes include:

- `docker-compose.yml`: app image pinned from `unillett/variance:latest` to a
  digest.
- `docker/config/variance.conf`: security header/server-token hardening.
- `docker/config/variance.ini`: `expose_php = Off`.

There is also a local backup file:

```text
/var/www/variance/docker-compose.yml.backup-20260521-pin-variance-digest
```

Rollback planning must preserve these local production changes, not only the
Git commit.

### Existing Backups

`/var/www/dumps` contains daily backups for several apps.

Latest observed Variance dumps:

```text
20260522000501.variance-dev.backup.sql   74K
20260522001001.variance-prod.backup.sql  87K
```

These prove a backup job exists, but the dumps are small. Before migration or
cutover, verify content, table coverage, row counts, restoreability, and whether
these dumps represent the complete legacy production state.

Follow-up preflight confirmed the small dump size is plausible for the current
legacy database shape. The legacy production DB container has one application
schema:

```text
variance
```

Exact row counts observed in `variance-mysql-prod`:

```text
authors      10
works        18
versions     78
comparisons  57
chapters     813
```

The legacy dev DB container also has one application schema named `variance`.
Exact row counts observed in `variance-mysql-dev`:

```text
authors      2
works        2
versions     7
comparisons  5
chapters     872
```

Backup scheduling is currently in `deployer`'s crontab. It runs nightly dumps
for several apps, including:

- `variance-mysql-dev` around 00:05
- `variance-mysql-prod` around 00:10
- dump retention around 00:45 for matching SQL dump files older than 15 days

Important security note: the current crontab contains inline database
passwords. Do not copy the raw crontab into documentation, tickets, or chat.
For `variance-input`, prefer an application-owned backup command or a script
that reads credentials from an env file with restricted permissions rather than
embedding credentials directly in the crontab command line.

### Legacy Config Key Inventory

The legacy `/var/www/variance/.env` exists. Only key names were inspected; values
were not recorded.

Observed keys:

```text
MYSQL_DATABASE
MYSQL_PASSWORD
MYSQL_ROOT_PASSWORD
MYSQL_SERVERNAME_DEV
MYSQL_SERVERNAME_PROD
MYSQL_USER
PMA_ARBITRARY
```

The running MySQL containers also expose `MYSQL_*` environment keys. Secret
values were not printed. This is enough to confirm that DB access for
preflight/migration can be performed from inside the existing containers without
copying credentials into the planning document.

### Local And NAS Headroom

Observed headroom:

```text
/      xfs   51G used 6.6G available 45G use 13%
/var   xfs   90G used 27G  available 64G use 30%
NAS    cifs 200G used 36G  available 165G use 18%
```

Disk space does not currently look like the immediate blocker for a parallel
`variance-input` rehearsal, but image pulls/builds and DB restores still need a
specific size estimate.

## Multi-App Host Safety

Because `plett` hosts multiple apps, reconnaissance must identify and protect:

- Apache/httpd vhosts and enabled sites for all applications.
- Existing ports bound on localhost and public interfaces.
- Docker projects and container names for all applications.
- Shared system services that must not be restarted unnecessarily.
- Shared NAS credentials and mount definitions.
- Disk usage on `/`, `/var`, `/var/www`, `/var/log`, and NAS mountpoints.
- TLS certificate ownership and renewal mechanism.

Cutover should prefer a narrow vhost/proxy change for `variance.unil.ch`, with
configuration test before reload. Avoid broad Apache restarts when a reload is
sufficient and validated.

## Required Reconnaissance Before Execution

Read-only commands to capture current state:

```bash
cat /etc/os-release
hostnamectl
df -hT
findmnt -t cifs
findmnt /var/www/variance/uploads
findmnt /var/www/variance/uploads_images
stat -c '%U:%G %a %n' /var/www/variance/uploads /var/www/variance/uploads_images
grep -R "variance/D2C" /etc/fstab /etc/systemd/system /etc/systemd/system/*.mount 2>/dev/null
ss -tulpen
docker ps --format '{{.Names}} {{.Image}} {{.Status}} {{.Ports}}'
docker compose ls
apache2ctl -S
apache2ctl -M
apache2ctl configtest
systemctl status apache2
```

If `plett` is RHEL/httpd rather than Ubuntu/apache2, use the equivalent:

```bash
httpd -S
httpd -M
httpd -t
systemctl status httpd
```

Do not share command output containing secrets. Redact any inline credentials,
tokens, private keys, or password values.

## Information Still Needed

### Host And App Inventory

- Exact OS and Apache/httpd variant on `plett`.
- Full vhost list and which vhost currently owns `variance.unil.ch`.
- Whether `variance.unil.ch` is served directly by Apache or via an upstream
  proxy/load balancer.
- Other apps hosted on `plett`, their vhosts, ports, Docker projects, and
  operational sensitivity.
- Whether any app shares `/var/www/variance`, `/var/www/variance-input`, NAS
  credentials, or database services.

### Legacy Production App

- Current legacy document root and Apache vhost file.
- Current legacy PHP runtime model.
- Current legacy database location, credentials source, and backup mechanism.
- Current legacy cron jobs or scheduled maintenance tasks.
- Current production data freshness compared with `variance-input` staging/test.
- Whether editors can currently change data in legacy production, and whether a
  freeze window is required.

### `variance-input` Production Target

- Final deploy root on `plett`.
- Final compose file and whether a separate production compose file is needed.
- Final image tags. Production must use pinned release tags, not `stage-latest`
  or `latest`.
- Final environment file values:
  - `APP_ENV=production`
  - `APP_URL=https://variance.unil.ch`
  - `ADMIN_BASE_PATH=/admin`
  - `APP_GIT_SHA=<deployed commit>`
  - stable session/cache/Redis prefixes
  - database credentials
  - queue connection
  - mail settings, if needed
- Production will use a database container in the `variance-input` compose
  project. No external database is planned.
- Laravel private storage, DB backups, and Medite runtime data live under
  `/var/www/variance-input/var` as described in the production data layout.
- `/var/www/variance-input/var/uploads_pdf` resolves to
  `/var/www/variance-input/var/uploads/pdf` so PDFs remain in the NAS-backed
  uploads tree.

### Production Database Container

Planning decision: `variance-input` production needs its own DB container.

Current implication:

- Use a MariaDB container managed by the `variance-input` compose project.
- Persist database data outside ephemeral containers, likely under the
  `variance-input` deploy root as `./var/dbdata` or an explicitly named Docker
  volume.
- Keep this separate from the legacy volumes:
  - `variance_mysql-data`
  - `variance_mysql-data-dev`
- Do not reuse, rename, or remove the legacy DB volumes during first cutover.
- Before any migration/import, take and verify a fresh legacy DB dump for
  rollback/audit.
- Restore the new production DB container from the staging `variance-input`
  dump, not from the old legacy prod DB.
- Confirm backup scheduling for the new DB container before cutover.

### Data Migration And Media

- Final legacy SQL dump source and restore procedure for rollback/audit.
- Final staging `variance-input` DB export source and restore procedure into the
  new prod DB container.
- Whether the latest legacy production data has already been imported into
  staging `variance-input`, and whether there has been any legacy production
  delta since that import.
- Checksums or counts for:
  - text versions
  - comparison folders
  - facsimile folders
  - manifest JSON files
  - work cover images
  - PDFs
- Whether dev/draft outputs should be preserved exactly.
- Whether any paths in the legacy database assume `/var/www/variance` literally.
- Whether any staging-created records need production-specific owner/user
  mapping or publication flag review.

### TLS, DNS, And Proxy

- TLS certificate path and renewal mechanism.
- Whether HSTS is enabled for `variance.unil.ch`.
- Whether Apache terminates TLS and proxies to `variance-input` on localhost.
- Required proxy headers:
  - `Host`
  - `X-Forwarded-Proto`
  - `X-Forwarded-Host`
  - `X-Forwarded-Prefix` for `/admin`
- Whether DNS changes are needed, or only local vhost routing.

### Validation Corpus

Use the validation corpus table above and fill exact URLs/IDs before rehearsal.

## Rehearsal Plan

1. Build or pull pinned production-candidate images.
2. Deploy `variance-input` beside legacy on a non-public localhost port.
3. Mount NAS shares at new `variance-input` paths.
4. Restore or connect the intended production database copy.
5. Run migrations only after verified DB dump.
6. Sync Vite assets if the deployment model uses the `vite_build` volume.
7. Validate:
   - `/`
   - `/dev`
   - `/admin/`
   - `/health`
   - `/admin/health/report`
   - representative public comparison pages
   - facsimile image display
   - PDF links
   - Medite task launch/polling, if allowed in rehearsal
   - publication to `dev`
   - publication to `prod`
   - legacy export bundle generation
8. Warm reader artifacts for selected large legacy works if needed.
9. Record all gaps before approving cutover.

## Cutover Outline

This is a planning outline, not an execution procedure.

1. Confirm change window and editor freeze.
2. Confirm rollback owner and communication channel.
3. Take final database dump and verify it is non-empty.
4. Confirm NAS mounts and available space.
5. Confirm legacy vhost/config backup.
6. Confirm `variance-input` is healthy on localhost.
7. Switch only the `variance.unil.ch` routing to `variance-input`.
8. Run Apache/httpd config test.
9. Reload Apache/httpd.
10. Smoke test public and admin routes.
11. Monitor logs, queues, scheduler heartbeat, and disk usage.

## Rollback Outline

Rollback must keep the old legacy app available without moving media folders.

1. Restore the previous `variance.unil.ch` vhost/proxy target.
2. Run Apache/httpd config test.
3. Reload Apache/httpd.
4. Verify legacy public site at `https://variance.unil.ch/`.
5. Keep `variance-input` containers stopped or isolated until cause is known.

Do not delete `variance-input` data during rollback. Preserve logs and state for
analysis.

## Success Criteria

- `https://variance.unil.ch/` serves the expected public Variance site.
- `/admin/` is reachable and authenticated users can access the admin UI.
- `/health` is healthy.
- `/admin/health/report` shows queue and scheduler heartbeats.
- Public comparison pages load representative text, images, and PDFs.
- Editors can publish a representative comparison to `dev`.
- Editors can publish a representative comparison to `prod`.
- Existing legacy folders under `/var/www/variance` remain intact for rollback.
- No unrelated app on `plett` has changed behavior.

## Initial Timeline Target

Assuming today is 2026-05-22:

- 2026-05-22 to 2026-05-27: reconnaissance and production request/policy file.
- 2026-05-28 to 2026-06-02: prod-like rehearsal beside legacy.
- 2026-06-03 to 2026-06-05: stakeholder validation and cutover decision.
- 2026-06-08 to 2026-06-10: production cutover window if rehearsal is clean.
  Target cutover deadline: 2026-06-10 or before.
