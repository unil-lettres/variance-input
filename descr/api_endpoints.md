# Admin API Reference (Partial)

Key endpoints exposed by the Laravel admin app. Routes are defined in `laravel/routes/api.php` and `laravel/routes/web.php` (for AJAX endpoints).

---

## Versions

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/versions?work_id={id}` | List versions for a work (includes pagination and facsimile status). |
| POST | `/api/versions` | Upload a new textual version (multipart, fields: `work_id`, `name`, `versionFile`). |
| POST | `/api/versions/{version}/lignes` | Upload `_lignes` file; queues sidecar generation. |
| GET | `/api/versions/{version}/page-markers/progress` | Poll version-level pagination progress. |
| GET | `/api/versions/{version}/comparisons` | List comparisons involving the version (used for manifest selection). |
| PUT | `/api/versions/{version}/manifests/{comparison}` | Save manifest images for the given comparison/role. |
| DELETE | `/api/versions/{version}` | Remove a version if not used in comparisons. |

## Comparisons

| Method | Path | Description |
|--------|------|-------------|
| GET | `/comparisons/by-work?work_id={id}` | List comparisons with status (pagination, manifests, publish flags). |
| POST | `/api/run_medite` | Launch Medite run for a source/target version pair. |
| POST | `/api/comparisons/{comparison}/page-markers` | Queue pagination injection into comparison XHTML. |
| GET | `/api/comparisons/{comparison}/page-markers/progress` | Poll comparison-level pagination progress. |
| GET | `/comparisons/{comparison}/manifests/{role}` | Download the JSON manifest used for the given role (source/target). |
| POST | `/api/publish_xhtml` | Publish a comparison’s artefacts to the legacy tree. |
| DELETE | `/api/publish_xhtml/{comparison}` | Unpublish comparison. |
| GET | `/comparisons/{comparison}/export` | Download the published comparison + manifest-selected facsimiles. |
| DELETE | `/api/comparisons/{comparison}` | Delete comparison (if not published). |

## Facsimiles

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/upload_facsimiles` | Upload facsimile batch for a version (multipart). |
| GET | `/api/facsimiles?version_id={id}` | List processed facsimiles for the selected version (gallery). |
| DELETE | `/api/versions/{version}/facsimiles` | Cancel facsimile processing & delete drafts. |
| GET | `/api/versions/{version}/facsimiles/progress` | Poll facsimile processing progress. |

## Medite / External

| Method | Path | Description |
|--------|------|-------------|
| POST | `http://medite:5000/run_diff2` | (Internal) Called by Laravel to start a diff. |
| GET | `http://medite:5000/task_status/{id}` | Check Celery job status. |

---

### Notes

- Most admin endpoints require the `admin` guard (handled via Sanctum/session; see `routes/web.php`).
- Uploaded files use standard Laravel `multipart/form-data` with CSRF token for browser calls.
- Versions/comparisons IDs refer to database identifiers.
