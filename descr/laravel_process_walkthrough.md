# Variance — Laravel Process Walkthrough

This is a very short walkthrough for explaining how the Laravel part of Variance works.

The easiest mental model is:

1. a request reaches Laravel
2. Laravel matches a route
3. middleware checks access or maintenance state
4. a controller handles the action
5. models, services, and storage are used
6. Laravel returns HTML or JSON
7. the frontend updates, or a queue job continues the work

## 1. Entry Point

For admin traffic, the request usually comes through nginx:

- `/admin` and `/health` go to Laravel
- the public `/` site goes to legacy PHP

Inside Laravel, the main route files are:

- [web.php](/Users/jganivet/Développement/variance2/laravel/routes/web.php:1)
- [api.php](/Users/jganivet/Développement/variance2/laravel/routes/api.php:1)

Important detail: JSON endpoints are split across both files, so the real starting point is still “find the route first”.

## 2. Standard Laravel Flow

For a normal admin request, the flow is:

1. the route matches a controller method
2. middleware such as `auth` or `admin` may run first
3. the controller validates input and permissions
4. the controller loads models or calls a service
5. Laravel reads or writes files in `storage/` when needed
6. a Blade view or JSON response is returned

Main code areas:

- controllers: [laravel/app/Http/Controllers](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers)
- services: [laravel/app/Services](/Users/jganivet/Développement/variance2/laravel/app/Services)
- models: [laravel/app/Models](/Users/jganivet/Développement/variance2/laravel/app/Models)
- views: [laravel/resources/views](/Users/jganivet/Développement/variance2/laravel/resources/views)

## 3. Example A: Open the Admin App

Example request:

```text
GET /admin
```

Flow:

1. nginx forwards `/admin` to Laravel
2. [web.php](/Users/jganivet/Développement/variance2/laravel/routes/web.php:1) matches `/`
3. `auth` decides whether to redirect to login
4. Laravel returns [main.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/pages/main.blade.php:1)
5. the browser then starts loading JSON data for works, versions, comparisons, and chapters

## 4. Example B: Load Versions

Example request:

```text
GET /api/versions?work_id=10
```

Flow:

1. the route points to `VersionController::index()`
2. the controller loads version rows for the selected work
3. Laravel also derives useful state such as facsimiles and pagination status
4. JSON is returned to the admin UI

Main file:

- [VersionController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/VersionController.php:24)

Typical files used by this process:

- `storage/app/public/uploads/versions/{folder}.txt`
- `storage/app/public/uploads/versions/{folder}.xml`
- `storage/app/private/pagination/{version_id}.json`

## 5. Example C: Queue-Based Laravel Work

Some Laravel actions do not finish everything inside the HTTP request.

Example:

```text
POST /api/versions/{id}/lignes
```

Flow:

1. the controller stores the uploaded `_lignes` file
2. Laravel dispatches `ApplyLignesJob`
3. the queue worker processes the file in background
4. Laravel writes the pagination sidecar JSON
5. the frontend polls progress and updates when ready

Main files:

- [VersionController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/VersionController.php:1)
- [ApplyLignesJob.php](/Users/jganivet/Développement/variance2/laravel/app/Jobs/ApplyLignesJob.php:1)
- [PageMarkerService.php](/Users/jganivet/Développement/variance2/laravel/app/Services/PageMarkerService.php:1)

This pattern is important in Variance:

- HTTP request starts the action
- queue job does the heavy work
- Laravel stores the result for later reads

## 6. One-Sentence Summary

Laravel in Variance is the orchestration layer for the admin app: it receives the request, applies access rules, coordinates models/services/files/jobs, and returns either a Blade page, a JSON payload, or an async task state.
