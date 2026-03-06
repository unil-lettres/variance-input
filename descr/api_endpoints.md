# Admin API Reference (Partial)

Key endpoints exposed by the Laravel admin app. Routes are defined in `laravel/routes/api.php` and `laravel/routes/web.php` (for AJAX endpoints).

---

## Works & Authors

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/authors` | List authors. |
| POST | `/api/authors` | Create an author. |
| PUT | `/api/authors/{id}` | Update an author. |
| DELETE | `/api/authors/{id}` | Delete an author (if allowed). |
| GET | `/api/author/{authorId}/works` | List works for an author. |
| POST | `/api/works` | Create a work. |
| GET | `/api/works/{id}` | Fetch a work. |
| PUT | `/api/works/{id}` | Update work title (short_title is locked once used). |
| DELETE | `/api/works/{id}` | Delete a work (only if no versions). |
| GET | `/works/{id}/can-edit` | Check if current user can edit a work (Work policy). |
| GET | `/works/{workId}/status` | Read work status checkboxes. |
| POST | `/works/{workId}/status` | Update work status checkboxes. |
| GET | `/works/{id}/description` | Read work description. |
| POST | `/works/{workId}/description` | Update work description. |

## Media (Works)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/works/{work}/media` | Fetch cover image + PDF URLs. |
| POST | `/api/works/{work}/media` | Upload/replace vignette and/or PDF (multipart). |
| DELETE | `/api/works/{work}/media/{type}` | Delete `vignette` or `pdf`. |

## Accounts & Admin

| Method | Path | Description |
|--------|------|-------------|
| GET | `/account/password` | Password change form. |
| POST | `/account/password` | Update password. |
| GET | `/users` | Admin user management list. |
| POST | `/users` | Create a user (admin only). |
| PATCH | `/users/{user}` | Update a user (admin only). |
| DELETE | `/users/{user}` | Delete a user (admin only). |
| GET | `/tasks` | Queue/task monitor (admin only). |
| GET | `/health` | JSON health report. Includes `checks.app.git_sha`, `git_sha_short`, and severity status: `ok` (green), `degraded` (orange warning), `fail` (red critical). |
| GET | `/health/report` | HTML health report (admin only), including the same severity and git SHA information. |

## Versions

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/versions?work_id={id}` | List versions for a work. Uses a short cache; pass `fresh=1` to bypass. `text_length` and facsimile status are lazy (see endpoints below). |
| POST | `/api/versions` | Upload a new textual version (multipart, fields: `work_id`, `name`, `versionFile`). |
| GET | `/api/versions/{version}/text-length` | Return the UTF-8 character count for the raw TXT (lazy-loaded for the UI). |
| GET | `/api/versions/{version}/lignes` | Download the stored `_lignes` file. |
| POST | `/api/versions/{version}/lignes` | Upload `_lignes` file; queues sidecar generation. |
| POST | `/api/versions/{version}/page-markers` | Upload `_lignes` directly with `clear_existing` / `replace_existing` options. |
| POST | `/api/versions/{version}/pagination/from-pb` | Build pagination sidecar from `<pb>` tags in the TEI. |
| GET | `/api/versions/{version}/page-markers/progress` | Poll version-level pagination progress. |
| GET | `/api/versions/{version}/pagination-info` | Read sidecar metadata (marker count, file size, timestamps). |
| PATCH | `/api/versions/{version}/pagination/done` | Toggle the `pagination_done` flag for a version. |
| DELETE | `/api/versions/{version}/lignes` | Cancel in-progress pagination work for the version. |
| DELETE | `/api/versions/{version}/lignes/file` | Delete the stored `_lignes` file for the version. |
| GET | `/api/versions/{version}/comparisons` | List comparisons involving the version (used for manifest selection). |
| PUT | `/api/versions/{version}/manifests/{comparison}` | Save manifest images for the given comparison/role. |
| POST | `/api/facsimiles/publish` | Publish facsimiles for a version (legacy mirror). |
| DELETE | `/api/versions/{version}/facsimiles/cancel-upload?restore_previous=1` | Cancel the current facsimile folder upload, purge partial files, and restore the previous series when a backup exists. |
| POST | `/versions/{version}/facsimiles/toggle-ignored` | Toggle ignored facsimile page for a version. |
| DELETE | `/api/versions/{version}` | Remove a version if not used in comparisons. |

## Comparisons

| Method | Path | Description |
|--------|------|-------------|
| GET | `/comparisons/by-work?work_id={id}` | List comparisons with status (pagination, manifests, publish flags). |
| GET | `/comparisons/by-work?work_id={id}&light=1` | List comparisons with minimal payload; returns `details_loaded=false` so the UI can fetch per-row details. |
| GET | `/comparisons/{comparison}/details` | Fetch heavy comparison status (components, manifests, publish flags, XML availability, etc.). |
| GET | `/api/comparisons/publication-counts` | Get global prod/dev publication counts for the Sites dropdown. |
| POST | `/api/comparisons` | Create a comparison metadata row (used before running Medite). |
| POST | `/api/run_medite` | Launch Medite run for a source/target version pair. |
| POST | `/api/comparisons/{comparison}/page-markers` | Queue pagination injection into comparison XHTML (`role`, `clear_existing`, `replace_existing`). |
| GET | `/api/comparisons/{comparison}/page-markers/progress` | Poll comparison-level pagination progress. |
| POST | `/api/comparisons/{comparison}/page-markers/cancel` | Cancel pagination injection for a comparison (optional `role`). |
| POST | `/api/comparisons/{comparison}/page-markers/restore` | Restore the original comparison outputs (optional `role`). |
| POST | `/api/comparisons/{comparison}/pagination/from-xhtml` | Build pagination sidecar from `<pb>` tags in comparison outputs. |
| GET | `/comparisons/{comparison}/manifests/{role}` | Download the JSON manifest used for the given role (source/target). |
| POST | `/api/publish_xhtml` | Publish a comparison’s artefacts to prod or dev (JSON: `comparison_id`, `destination=prod|dev`, optional `insert_default_marker`). |
| DELETE | `/api/publish_xhtml/{comparison}` | Unpublish comparison. |
| GET | `/comparisons/{comparison}/export` | Download the published comparison + manifest-selected facsimiles. |
| DELETE | `/api/comparisons/{comparison}` | Delete comparison (if not published). |

## Editors

| Method | Path | Description |
|--------|------|-------------|
| GET | `/version/{version}/editor` | Load the version TEI editor. |
| PUT | `/version/{version}/editor` | Save the version TEI XML. |
| GET | `/comparison/{comparison}/editor` | Load the comparison XHTML editor. |
| PUT | `/comparison/{comparison}/editor` | Save comparison XHTML (requires unpublished + manifest JSON). |

## Facsimiles

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/upload_facsimiles` | Upload facsimile batch for a version (multipart). |
| GET | `/api/facsimiles?version_id={id}` | List processed facsimiles for the selected version (gallery). |
| GET | `/api/facsimiles/space?required_bytes={n}` | Check available disk space before facsimile upload. |
| DELETE | `/api/versions/{version}/facsimiles` | Cancel facsimile processing & delete drafts. |
| GET | `/api/versions/{version}/facsimiles/progress` | Poll facsimile processing progress. |

## Medite / External

| Method | Path | Description |
|--------|------|-------------|
| POST | `http://medite:5000/run_diff2` | (Internal) Called by Laravel to start a diff. |
| GET | `http://medite:5000/task_status/{id}` | Check Celery job status. |
| GET | `/api/task_status/{taskId}` | Proxy task status via Laravel (used by UI). |

---

### Notes

- All admin endpoints require authentication; non-admin users are scoped to their own comparisons (legacy comparisons are read-only for them).
- Uploaded files use standard Laravel `multipart/form-data` with CSRF token for browser calls.
- Versions/comparisons IDs refer to database identifiers.
