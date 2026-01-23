# Facsimile Ingestion & Publication

This note documents the lifecycle of facsimile images in the Variance stack, from upload through publication.

---

## 1. Upload (Admin UI)

1. From the **Versions** card, click “Téléverser des fac-similés” (or use the dedicated button inside the facsimile blade).
2. Pick the JPEG/PNG files to ingest; the UI posts them to `FacsimileController::store`.
3. The controller writes each file to `storage/app/facsimile_queue/{author}/{work}/{version}` and dispatches a `ProcessFacsimileImage` job for further processing.

## 2. Batch Processing

- Jobs run on the `facsimiles` queue (handled by the `laravel-queue` container).
- Responsibilities:
  * Ensure destination directory exists in `storage/app/public/uploads/{author}/{work}/{version}`.
  * Generate thumbnails (`img_*_thumb.jpg`) without touching originals.
  * Keep filenames normalised (`img_<version>_<index>.jpg`), which matches pagination markers and manifests.
- Progress is reflected in the modal and triggers a `facsimilesUploaded` event so the gallery reloads automatically.

## 3. Manifest Curation

- The facsimile blade exposes a “Gestion du manifeste” selector populated via `/api/versions/{version}/comparisons`.
- Selecting a comparison/role loads the current manifest and highlights chosen images.
- Saving sends `PUT /api/versions/{version}/manifests/{comparison}` (payload: `{ role, images: [] }`).
- Laravel persists the manifest under `storage/app/public/uploads/{author}/{work}/{version}/images_{role}_{author--work--comparison}.json` and emits `comparisonManifestUpdated`, keeping the comparisons table badge (`JSON …`) in sync.
- Only images present in this manifest are published/exported for that comparison.

## 4. Publication (automatic)

Facsimiles are published automatically when a comparison is published (prod or dev):

1. The comparison publish endpoint calls `PublishController` to:
   - copy images to `variance/uploads/{author}/{work}/{version}`;
   - ensure a manifest JSON exists for the comparison/role.
2. The public viewer loads the manifest JSON from the version folder to display the images.

## 5. Export Bundle

The comparisons table “Exporter” button assembles a zip containing:

- The published comparison directory (`public/uploads/{author}/{work}/{comparison}`).
- Source/target manifest JSON files and **only** the images they reference.

This keeps exports lean while matching the legacy layout exactly.

## 6. Cleanup / Cancellation

- “Annuler le traitement” removes all queued batches and processed drafts for a version.
- Deleting a version will remove both the private queue folder and the published copies when possible.

## Notes & Tips

- Keep the queue worker running; otherwise uploads remain in the queue directory untouched.
- Manifest changes are version-specific; ensure the correct version is selected when editing a comparison.
- Image naming convention matters for pagination matching (`img_<version>_<index>.jpg`).
- For bulk reprocessing, clear the existing queue directory in `storage/app/private/facsimile_queue` and re-upload.
