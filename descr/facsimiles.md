# Facsimile Ingestion & Publication

This note documents the lifecycle of facsimile images in the Variance stack, from upload through publication.

---

## 1. Upload (Admin UI)

1. From the Versions card, click “Téléverser des fac-similés”.
2. Pick a directory that contains the source images (JPEG/PNG). The UI sends the file list to Laravel.
3. `FacsimileController::upload` stores the batch under `storage/app/private/facsimile_queue/{author}/{work}/{version}/{timestamp}` and queues processing jobs.

## 2. Batch Processing

- Jobs run on the `facsimiles` queue (handled by the `laravel-queue` container).
- Responsibilities:
  * Ensure destination directory exists in `storage/app/public/uploads/{author}/{work}/{version}`.
  * Generate resized derivatives (miniatures, manifests JSON).
  * Mirror drafts to the legacy tree (`variance/uploads/...`) for preview if needed.
- Progress is tracked via `storage/app/tmp/facsimiles/{version_id}.json` (polled by the UI).

## 3. Publication

Once processing is complete:

1. Click “Publier” on the version row.
2. `VersionController::publishFacsimiles` copies processed images from the private storage into the public tree (`variance/uploads/{author}/{work}/{version}`) and writes IIIF-like manifests (`images_{source|target}_*.json`) under the same branch.

## 4. Cleanup / Cancellation

- “Annuler le traitement” removes all queued batches and processed drafts for a version.
- Deleting a version will remove both the private queue folder and the published copies when possible.

## Notes & Tips

- Keep the queue worker running; otherwise uploads remain in the queue directory untouched.
- Image naming convention matters for pagination matching (`img_<orientation>_<number>.<ext>`). The pagination sidecar expects consistent `image` codes.
- For bulk reprocessing, clear the existing queue directory in `storage/app/private/facsimile_queue` and re-upload.
