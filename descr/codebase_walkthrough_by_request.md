# Variance — Code Walkthrough by Request

This walkthrough is meant for a simple oral presentation of the codebase.

The guiding idea is:

1. a request is received from the client
2. the app routes it
3. a controller handles it
4. models / services / files are loaded
5. a response is returned
6. frontend code renders the result

That is easier to explain than starting with a raw folder tour.

## 1. First Principle

For this codebase, the easiest mental model is:

- browser action
- URL
- route
- controller
- service / model / storage
- response
- UI update

There are two main worlds behind the proxy:

- Laravel handles `/admin` and admin/API requests
- legacy PHP handles the public site under `/`

The front door is the nginx proxy:
- [docker-compose.yml](/Users/jganivet/Développement/variance2/docker-compose.yml:1)
- [nginx/default.conf](/Users/jganivet/Développement/variance2/nginx/default.conf:1)

## 2. Example A: Opening the Admin App

Example request:

```text
GET /admin
```

Flow:

1. nginx receives `/admin`
2. nginx forwards it to the `laravel` container
3. Laravel matches the request in:
   - [web.php](/Users/jganivet/Développement/variance2/laravel/routes/web.php:1)
4. Laravel returns the main editorial page:
   - [main.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/pages/main.blade.php:1)
5. That page includes the main admin components:
   - work selector
   - description
   - versions
   - comparisons
   - chapters
6. Frontend JavaScript then starts loading the dynamic data for the selected work

Main files to show:

- [web.php](/Users/jganivet/Développement/variance2/laravel/routes/web.php:1)
- [main.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/pages/main.blade.php:1)
- [work_selector.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/work_selector.blade.php:1)

## 3. Example B: Loading Versions for a Work

Example request:

```text
GET /api/versions?work_id=10
```

This happens after the user selects a work in the admin interface.

Flow:

1. the browser sends the request
2. Laravel matches the route to `VersionController`
3. `VersionController::index()` loads versions for the selected work
4. Laravel also computes some useful derived state:
   - facsimile counts
   - pagination status
   - whether the version is already used in comparisons
5. JSON is returned to the browser
6. the versions panel renders the rows

Main files:

- [VersionController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/VersionController.php:24)
- [versions.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/versions.blade.php:1)

Important storage used later by this part of the app:

- version TXT:
  - `laravel/storage/app/public/uploads/versions/{folder}.txt`
- version XML:
  - `laravel/storage/app/public/uploads/versions/{folder}.xml`
- `_lignes` file:
  - `laravel/storage/app/private/lignes/{version_id}.txt`
- pagination sidecar:
  - `laravel/storage/app/private/pagination/{version_id}.json`

## 4. Example C: Opening the Viewer for One Version

Example request:

```text
GET /api/versions/25/reader
```

This is one of the best walkthrough examples because it crosses many layers.

Flow:

1. the user clicks the eye icon in the versions table
2. frontend code sends `/api/versions/{id}/reader`
3. route resolves to:
   - `VersionController::readerData()`
4. the controller builds or loads a reader dataset
5. the dataset includes:
   - text
   - chosen text source
   - facsimiles
   - pagination markers
   - page plans
6. the controller returns JSON
7. viewer frontend renders:
   - thumbnail strip
   - image pane
   - text pane

Main backend methods:

- [VersionController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/VersionController.php:773)
- `readerData()`
- `readerDataset()`
- `buildReaderDatasetBundle()`
- `assembleReaderDatasetPayload()`
- `readerPagePlans()`
- `materializeReaderPage()`

Important service:

- [PageMarkerService.php](/Users/jganivet/Développement/variance2/laravel/app/Services/PageMarkerService.php:1)

What the reader may load:

1. version TXT if available
2. fallback text reconstructed from comparison XHTML if needed
3. pagination sidecar if available
4. runtime markers extracted from comparison XHTML if sidecar is missing
5. facsimile metadata from the version image folder

Relevant frontend:

- [facsimiles.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/facsimiles.blade.php:1)

Important teaching point:

The viewer is not just “show text and image”.
It is a small pipeline:

- choose text source
- resolve markers
- map markers to facsimiles
- slice text into pages
- return current page + navigation metadata

That is why viewer bugs often come from:

- bad marker extraction
- stale reader cache
- wrong facsimile-image mapping

## 5. Example D: Loading Comparisons for a Work

Example request:

```text
GET /comparisons/by-work?work_id=10&light=1
```

Flow:

1. the comparisons panel asks for all comparisons of the selected work
2. route resolves to `ComparisonController`
3. controller loads comparisons through source/target versions
4. response contains comparison metadata such as:
   - source version
   - target version
   - publication status
   - comment status
   - chapter presence
5. the comparisons table renders the result

Main files:

- [ComparisonController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/ComparisonController.php:1)
- [comparisons.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/comparisons.blade.php:1)
- [comparisons.js](/Users/jganivet/Développement/variance2/laravel/public/js/comparisons.js:1)

## 6. Example E: Launching a Medite Comparison

Example request:

```text
POST /api/run_medite
```

Flow:

1. the user clicks “Lancer un alignement”
2. Laravel receives the request
3. controller validates chosen source and target versions
4. Laravel prepares the source files for Medite
5. the request is sent to the Flask `medite` service
6. Medite generates:
   - comparison XML
   - source XHTML
   - target XHTML
   - diff fragments
7. Laravel stores comparison metadata in DB
8. the UI refreshes the comparisons table

Main files:

- [MediteController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/MediteController.php:1)
- [flask_app.py](/Users/jganivet/Développement/variance2/medite/app/flask_app.py:1)
- [diff.py](/Users/jganivet/Développement/variance2/medite/app/variance/scripts/diff.py:1)

Important storage:

- comparison working outputs:
  - `uploads/{author}/{work}/comparisons/{comparison_id}/`
- legacy mirror:
  - `variance/uploads/{author}/{work}/comparisons/{comparison_id}/`

## 7. Example F: Publishing a Comparison

Example request:

```text
POST /api/publish_xhtml
```

Flow:

1. the user clicks “Publier”
2. Laravel receives the publication request
3. `PublishController` validates:
   - comparison state
   - destination (`dev` or `prod`)
   - marker insertion option if requested
4. publication copies the generated comparison outputs to the right place
5. facsimiles and manifests are also mirrored if needed
6. the legacy PHP public site can then serve the published comparison

Main files:

- [PublishController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/PublishController.php:1)

Important distinction:

- `dev` publication keeps data in the comparison-style path
- `prod` publication copies data into the public comparison folder expected by the legacy public site

This is why publication logic has a lot of file-system handling.

## 8. Example G: Importing Chapters

Example request:

```text
POST /chapters/preview
POST /chapters/commit
```

Flow:

1. user selects a comparison target in the `Chapitres` panel
2. user uploads an `.xlsx`
3. Laravel parses the workbook
4. preview is returned first
5. user confirms the import
6. chapter rows are written into the `chapters` table
7. the public comparison can later use those rows for chapter navigation

Main files:

- [ChaptersController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/ChaptersController.php:1)
- [ChapterImportService.php](/Users/jganivet/Développement/variance2/laravel/app/Services/ChapterImportService.php:1)
- [Chapter.php](/Users/jganivet/Développement/variance2/laravel/app/Models/Chapter.php:1)
- [chapters.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/chapters.blade.php:1)

Teaching point:

This feature is comparison-oriented, not version-only:

- it uses source/target anchors
- it belongs to a comparison context

## 9. Example H: Public Site Request

Example request:

```text
GET /dev/tests_jg/une_page_damour/comparaison/85
```

Flow:

1. nginx routes `/` traffic to the legacy PHP public app
2. legacy PHP reads published files from:
   - `variance/uploads/...`
3. chapter navigation, facsimile manifests, and published XHTML are loaded there
4. the public page renders the comparison

This is important to explain because the app is hybrid:

- Laravel is the editorial back office
- legacy PHP is still the public delivery layer

## 10. Suggested Oral Walkthrough Order

For a colleague, the cleanest order is:

1. nginx decides whether the request goes to Laravel or legacy PHP
2. `/admin` loads the main Laravel editorial UI
3. selecting a work triggers versions and comparisons requests
4. opening a version viewer triggers the reader pipeline
5. launching Medite creates comparison files
6. publishing exposes those files to the public site
7. the public site still reads the legacy mirror

## 11. One-Sentence Summary

Variance is easiest to understand as:

an nginx-routed hybrid application where Laravel manages editorial actions and file generation, while the legacy PHP public site still serves the published artifacts mirrored on disk.
