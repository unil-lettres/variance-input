# Pagination Editor & Sidecar Alignment

This note captures the work needed to align the in-app CodeMirror editor with the canonical pagination flow (TEI `<pb>` markers → `_lignes` sidecar → `<span class="page-marker">…` injection). The goal: editors insert semantic `<pb>` tags inside versions, Medite keeps them in every comparison, and the pagination jobs still control display-level markers.

## Current situation

- The CodeMirror toolbar inserts full `<span class="page-marker" …>` blocks (see `resources/js/codemirror-editor.js:~570`). Those spans are meant for XHTML comparisons, not TEI sources.
- Variance versions are uploaded as `.txt` → wrapped into TEI via `VersionController::store`. Only a small whitelist of inline tags survive; we recently reintroduced `<pb>` to keep pagination hints from TXT fixtures.
- Medite reads the TEI files referenced by a version folder (via `MediteController::convertPath`). Any inline `<span class="page-marker">…` markup gets copied to the comparison, but that markup is brittle (role-specific orientation, icon path, data-image-name padding) and bypasses the `_lignes` sidecar.
- The downstream pagination job (`InjectComparisonPaginationJob` / `PageMarkerService::insertComparisonMarkers`) expects to own `<span class="page-marker">` blocks. It clears/replaces them per comparison and ensures orientation matches source/target roles.

## Desired behaviour

1. **Editing** – The CodeMirror editor should seed semantic placeholders (`<pb facs="…" pagination="…"/>`) rather than the final `<span>` block.
2. **Persistence** – `VersionController::store` already whitelists `<pb>` so uploads and inline edits keep those nodes intact.
3. **Sidecar compatibility** – `_lignes` parsing / pagination JSON stays the single source of truth for offsets, letting the job inject `<span class="page-marker">…` blocks into comparisons (and eventually swap or remove them) without relying on editors crafting perfect HTML.

## Implementation plan

### 1. Editor changes (frontend)
- [ ] Update `resources/js/codemirror-editor.js`
  - Replace `insertPageMarker()` payload with `<pb facs="…" pagination="…"/>`.
  - Adjust regexes/cache builders (search for existing markers, highlight logic, counters) to detect `<pb>` instead of `<span class="page-marker">`.
  - Ensure orientation-specific icon names are no longer referenced; if needed, capture target/source orientation as data attributes to help editors pick pagination codes.
  - Update error messages/tooltips to mention “balise `<pb>`” insertion.
- [ ] Adapt the editor Blade (`resources/views/components/main/editor/version.blade.php`)
  - Revise any contextual help text describing page markers.
  - Consider adding a short cheat sheet (e.g., valid attributes: `facs`, `pagination`, optional `corresp`) so editors know what the CodeMirror button will inject.
- [ ] Rebuild assets (`npm run build`) once changes land.

### 2. Backend readiness
- [x] `VersionController::store` now preserves `<pb>` tags (already merged).
- [ ] Confirm `EditorController::versionUpdate` doesn’t sanitize away self-closing tags. If we add validation, ensure the regex accepts `<pb …/>`.
- [ ] (Optional) extend `PageMarkerService::countMarkersInVersion` to report `<pb>` counts as an early validation signal in the UI.

### 3. Sidecar alignment
- [ ] Document that `_lignes` remains the canonical input for pagination dispatch.
- [ ] (Optional stretch) add a console command or queue job that scans TEI `<pb>` markers and produces a draft `_lignes` file, so editors can bootstrap sidecars from inline hints.
- [ ] Verify that `InjectComparisonPaginationJob` behaves identically when comparisons already contain `<pb>` nodes (no change expected).

### 4. QA & rollout
- [ ] Unit-level sanity: add/update tests under `medite/app/variance/tests` to confirm `xml2medite` + `medite2xml` keep `<pb>` nodes round-trippable.
- [ ] Manual workflow test:
  1. Insert `<pb>` tags via CodeMirror in a test version.
  2. Run a comparison; confirm the resulting TEI/XHTML show the markers as verbatim `<pb>`.
  3. Upload `_lignes`, run pagination injection, and confirm `<span class="page-marker">` replaces the expected spots.
- [ ] Update `README.md` / `descr/workflow.md` to describe the new editing flow and the rationale (semantic markers in versions, visual markers injected later).

### 5. Communication & migration
- [ ] Prepare a short in-app notice or admin email explaining that the version editor now inserts `<pb>` tags; editors should re-run pagination injection to keep comparisons in sync.
- [ ] Audit existing versions that may already contain `<span class="page-marker">` blocks and consider stripping them (scripted cleanup) so the new workflow is consistent.

Each checkbox can become a ticket/task for the upcoming sprint. The biggest lift is the CodeMirror refactor plus UX messaging; backend pieces mostly stay as-is thanks to the recent `<pb>` whitelist update.

## Backlog complementaire (editeur de versions)

- [ ] Ajouter un outil d'italique dans l'editeur de versions (application/retrait via balisage TEI/UI dediee, sans symbole de substitution dans le TXT source).
- [ ] Ajouter un outil d'exposant dans l'editeur de versions (rendu et persistance editoriales, sans convention `^...^` dans le TXT source).
- [ ] Ajouter un outil d'insertion d'image in-texte dans l'editeur de versions (ancrage explicite au fil du texte, sans convention `[Image]` dans le TXT source).
- [ ] Ajouter un outil de gestion des appels de notes dans l'editeur de versions (insertion/edition des appels et contenu associe, sans convention manuelle `^1^` dans le TXT source).
- [ ] Executer un test d'import de version couvrant toutes les nouvelles options de nettoyage (alineas, doubles espaces, fins de ligne, bords de fichier, caracteres invisibles, fins de ligne, normalisation legacy) et documenter le resultat.

1. **Editor update**
   - Change `insertPageMarker` in `resources/js/codemirror-editor.js` to emit `<pb …/>` tags, probably with attributes `facs` (image code) and `pagination` (page label). Optionally infer orientation so editors know which suffix (e.g. `S‑1r`) to type.
   - Adapt helper routines (marker cache, highlighting, search) to match `<pb …/>` patterns instead of `<span class="page-marker">…`. Most logic only needs regex tweaks.
   - Update the UI hints/tooltips so editors understand they are inserting TEI markers, not final page widgets.

2. **TEI lifecycle**
   - Ensure the version editor writes back UTF‑8 TEI unchanged (already handled by `EditorController::versionUpdate`). No extra work unless we want validation (e.g., reject malformed `<pb>` tags).
   - On upload, expand the whitelist if we decide to support additional inline pagination hints (e.g., future `<milestone>`).

3. **Sidecar / pagination job**
   - No logic change: `_lignes` → JSON → `PageMarkerService::insertComparisonMarkers` should remain authoritative for producing `<span class="page-marker">…` blocks.
   - Optional enhancement: expose a “prefill `_lignes` from `<pb>`” tool so editors don’t have to maintain both; this can read `<pb>` nodes, map them to `{image,page,phrase}`, and create a draft `_lignes` file for review.

4. **Verification**
   - Regression tests: run Medite on a version containing `<pb>` markers to ensure they appear in TEI diff (`*.xml`) and raw XHTML outputs prior to pagination injection.
   - After injection, confirm `<span class="page-marker">` blocks match the sidecar regardless of any inline `<pb>` tags already present.

Deliverable: editor inserts `<pb>` tags, the pagination jobs still manage the display spans, and users can generate unlimited comparisons knowing their manual pagination hints survive upstream.
