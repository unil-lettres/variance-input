# Variance Laravel tests

This suite is intended to cover reusable editorial workflow rules, not only isolated helpers.

## Implemented coverage

- work and author management rules
- legacy read-only restrictions
- text-version import and normalization defaults
- pagination state and workflow API behavior
- facsimile upload queuing and legacy lockouts
- Medite comparison launch, ownership, and metrics persistence
- publication and unpublication side effects for comparisons

Implemented scenarios:

- creating a new editable work under a legacy author
- editable vs legacy work permissions
- legacy read-only rejection for work description updates
- work short-title immutability
- version import default normalization behavior
- stale pagination progress ignored for legacy versions
- pagination validation tracking on versions
- facsimile upload queuing for editable versions
- facsimile upload rejection for legacy versions
- Medite launch creating an owned comparison and calling the Flask service
- Medite task completion persisting runtime and memory metrics
- researcher comparison listing restricted to personal or legacy comparisons
- publish flow copying XHTML components, facsimiles, and manifests
- unpublish flow clearing published outputs and publication scope

## Run locally

From `laravel/`:

```bash
composer test
```

or:

```bash
vendor/bin/phpunit --testdox
```

Target just the workflow feature tests:

```bash
vendor/bin/phpunit tests/Feature/Workflow
```

## Conventions for new tests

- Prefer HTTP feature tests for editorial workflows over controller-unit tests.
- Use `RefreshDatabase` so each workflow test runs against real migrations.
- Reuse helpers from `tests/TestCase.php` for authenticated editor/admin setup.
- Exercise production storage paths when the workflow depends on generated files (`uploads/versions`, `_lignes`, pagination sidecars).
- Cover both editable and legacy/read-only paths when a feature behaves differently for imported legacy data.

## Remaining high-value layers

- multipage TIFF upload expansion and image post-processing details
- comparison refresh and UI polling behavior after Medite completion
- publication distinctions between `prod` and `dev`
- page-marker injection and pagination sidecar workflows on comparisons
- facsimile upload cancellation and series restoration
- comparison export and editor access rules
- media upload workflows for vignette and PDF
- non-admin authorization boundaries on work/media endpoints beyond current coverage
