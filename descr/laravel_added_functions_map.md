# Laravel Added Functions Map

This document summarizes the functions added in the Laravel-side diff `2c3c56c..4469070`.

Goal:
- list the added functions in a didactic way
- classify them by Laravel layer
- link them to actual folders and filenames
- show which MVC concepts and domain models they touch

The inventory covers `100` added functions/methods under `laravel/`.

## 1. Laravel Structure: Folders, Files, Layers

| Laravel layer | Folder | Files touched | Role in Variance |
| --- | --- | --- | --- |
| Controller | `laravel/app/Http/Controllers/` | `ComparisonController.php`, `VersionController.php` | HTTP entry points for admin actions and JSON APIs |
| Service / domain support | `laravel/app/Services/` | `PageMarkerService.php` | Non-HTTP business logic for pagination markers and text anchoring |
| Factory / test data | `laravel/database/factories/` | `AuthorFactory.php`, `ComparisonFactory.php`, `VersionFactory.php`, `WorkFactory.php` | Generates fake Eloquent records for tests |
| Front-end asset | `laravel/public/js/` | `comparisons.js`, `work_selector.js` | Browser-side behavior for admin screens |
| View / Blade component | `laravel/resources/views/components/main/` | `facsimiles.blade.php`, `medite.blade.php`, `versions.blade.php` | Blade-rendered UI plus inline JavaScript |
| View / page composition | `laravel/resources/views/pages/` | `main.blade.php` | Top-level admin page behavior |
| Feature tests | `laravel/tests/Feature/` | `ExampleTest.php` | End-user behavior checks |
| Test infrastructure | `laravel/tests/` | `TestCase.php` | Shared helpers and filesystem setup for tests |

## 2. MVC and Domain Model Mapping

Laravel MVC is not a strict three-box structure here. Variance uses:

- **Controllers** for request handling and orchestration
- **Models** for database records such as `Version`, `Comparison`, `Work`, `Author`, `User`, `Permission`
- **Views** for Blade templates and front-end interaction code
- **Services** for domain logic that should not live inside controllers

### Main Eloquent models touched by the new functions

| Model | Meaning in Variance | Mainly used by |
| --- | --- | --- |
| `Version` | A textual witness/version of a work | `VersionController`, `PageMarkerService`, reader UI, tests |
| `Comparison` | A comparison between two versions | `ComparisonController`, reader fallback logic, comparison UI, tests |
| `Work` | A literary work belonging to an author | work selector UI, test helpers |
| `Author` | An author owning works | work selector UI, test helpers |
| `User` | Authenticated admin/researcher account | tests, permissions, account access |
| `Permission` | Edit rights on authors/works | test helpers |

### Non-model data objects introduced by the new code

| Data object | Stored in | Purpose |
| --- | --- | --- |
| Version TXT source | `storage/app/public/uploads/versions/{folder}.txt` | Plain text basis for the new reader |
| Comparison XHTML fallback | `storage/app/public/uploads/.../source.xhtml` or `target.xhtml` | Rebuilds readable text when TXT is missing or unusable |
| Pagination sidecar | `storage/app/private/pagination/{version_id}.json` | Stores page markers independently from TEI |
| Reader cache artifact | `storage/app/private/reader_cache/{version_id}/...json` | Persists computed reader dataset for fast reloads |
| Facsimile images | `storage/app/public/uploads/{author}/{work}/{version}` and legacy mirror | Images aligned with version text |

## 3. Function Inventory by File

### A. Controllers

#### `laravel/app/Http/Controllers/ComparisonController.php`
Layer: Controller  
MVC role: receives HTTP reorder requests and updates comparison order  
Main models: `Comparison`

| Function | Description |
| --- | --- |
| `reorder` | Moves one comparison up or down and renumbers sibling comparisons consistently. |

#### `laravel/app/Http/Controllers/VersionController.php`
Layer: Controller  
MVC role: serves the new reader API, text normalization, and reader-cache orchestration  
Main models: `Version`, `Comparison`, indirectly `Work` and `Author`  
Main service dependency: `PageMarkerService`

##### Public API methods

| Function | Description |
| --- | --- |
| `readerData` | Returns the complete reader payload for a version: text metadata, facsimiles, page summaries, first page, and pagination status. |
| `readerPage` | Returns one reader page by index for lazy page loading in the UI. |
| `convertTextToUtf8` | Converts a version TXT file from a legacy encoding to UTF-8, then clears stale reader caches. |

##### Facsimile collection helpers

| Function | Description |
| --- | --- |
| `readerFacsimiles` | Returns facsimile images for reader use without image dimensions. |
| `readerFacsimilesDetailed` | Returns facsimile images with file size and dimensions. |
| `collectReaderFacsimiles` | Shared worker that scans either Laravel storage or the legacy mirror and builds normalized facsimile metadata. |

##### Reader dataset construction and caching

| Function | Description |
| --- | --- |
| `readerDataset` | Main entry point for getting a reader dataset, using cache and persisted artifacts when available. |
| `buildReaderDataset` | Actually assembles the dataset from TXT, fallback XHTML, pagination sidecar, and facsimiles. |
| `readerDatasetCacheKey` | Builds the in-memory cache key for one version/encoding/fingerprint combination. |
| `readerDatasetNonceKey` | Returns the cache key used to invalidate prior reader datasets. |
| `clearReaderDatasetCache` | Deletes persisted artifacts and bumps the cache nonce to force regeneration. |
| `readerDatasetArtifactRelativePath` | Computes the disk path of the persisted reader JSON artifact. |
| `loadReaderDatasetArtifact` | Reads a persisted reader artifact from disk and validates its fingerprint. |
| `storeReaderDatasetArtifact` | Writes a computed reader dataset to disk as JSON. |
| `removeReaderDatasetArtifacts` | Deletes all persisted reader artifacts for one version. |
| `readerDatasetFingerprint` | Describes the current state of text, sidecar, fallback XHTML, and facsimiles so cache/artifact reuse stays safe. |

##### Fallback text reconstruction from comparison XHTML

| Function | Description |
| --- | --- |
| `readerTextFromComparisonXhtml` | Tries to reconstruct readable text and markers from the newest usable comparison XHTML file. |
| `readerFirstComparisonXhtmlCandidate` | Returns the first candidate fallback comparison file for fingerprinting. |
| `readerComparisonXhtmlCandidates` | Finds possible `source.xhtml` or `target.xhtml` files for a version across storage and legacy mirror paths. |
| `extractReaderTextFromComparisonXhtml` | Converts XHTML content into plain text by stripping markup and preserving structural breaks. |

##### Page segmentation logic

| Function | Description |
| --- | --- |
| `readerPagePlans` | Builds page slices from resolved markers and facsimile images. |
| `materializeReaderPage` | Converts a page plan into the actual text segment shown to the user. |
| `readerGuessedPagePlans` | Builds heuristic pages when no explicit markers are available. |
| `readerNextBoundary` | Chooses a paragraph or line break near a target boundary to make guessed pages read naturally. |

##### Small normalization / utility helpers

| Function | Description |
| --- | --- |
| `readerImageCode` | Normalizes image names or page labels into a comparable numeric code. |
| `humanReadableSize` | Formats byte counts for the UI. |
| `assertVersionTextNormalizationAllowed` | Guards the UTF-8 conversion endpoint behind authentication. |

### B. Service Layer

#### `laravel/app/Services/PageMarkerService.php`
Layer: Service  
MVC role: domain logic shared by controllers and readers  
Main models/data: pagination sidecars, XHTML markers, plain text offsets

| Function | Description |
| --- | --- |
| `extractRuntimeMarkersFromComparisonHtml` | Exposes runtime extraction of page markers from comparison XHTML. |
| `refineResolvedMarkerIndex` | Improves a resolved marker offset by searching for a nearby phrase variant in normalized text. |
| `getPaginationSidecar` | Loads and validates the JSON pagination sidecar for a version. |
| `resolveMarkersForPlainText` | Re-anchors marker positions from TEI-oriented offsets onto the plain text actually shown in the reader. |

Interpretation in MVC terms:
- this file is not a controller and not a view
- it is a service layer that protects controllers from low-level text and pagination logic

### C. Factories

#### `laravel/database/factories/AuthorFactory.php`
#### `laravel/database/factories/ComparisonFactory.php`
#### `laravel/database/factories/VersionFactory.php`
#### `laravel/database/factories/WorkFactory.php`
Layer: test-data support  
MVC link: supports model creation for tests

| Function | Description |
| --- | --- |
| `definition` | Defines default fake attributes for the corresponding Eloquent model. |

These functions belong to Laravel's factory system rather than runtime MVC request flow, but they are essential for testing the model layer.

### D. Front-End JavaScript in the View Layer

#### `laravel/public/js/comparisons.js`
Layer: View/front-end  
MVC role: browser behavior for the comparisons table  
Main model concepts shown in UI: `Comparison`

| Function | Description |
| --- | --- |
| `escapeHtml` | Prevents unsafe HTML injection when rendering dynamic values. |
| `refreshComparisonReorderButtons` | Enables/disables reorder buttons based on current list state. |
| `swapComparisonNumbers` | Swaps display order values locally in the browser. |
| `moveComparisonRowLocally` | Moves a comparison row visually before server confirmation. |
| `loadComparisons` | Fetches and refreshes the comparison list for the selected work. |

#### `laravel/public/js/work_selector.js`
Layer: View/front-end  
MVC role: browser behavior for selecting authors and works  
Main model concepts shown in UI: `Author`, `Work`

| Function | Description |
| --- | --- |
| `syncDropdownButton` | Keeps a custom dropdown button label aligned with the selected option. |
| `rebuildDropdownMenu` | Rebuilds a custom dropdown menu from `<select>` options. |
| `syncAuthorDropdown` | Refreshes the author selector UI. |
| `syncWorkDropdown` | Refreshes the work selector UI. |
| `hasSelectedAuthor` | Tells whether an author is currently selected. |
| `syncAddWorkButtonState` | Enables or disables the “add work” action depending on context. |

### E. Blade Components with Inline Reader / UI Logic

#### `laravel/resources/views/components/main/facsimiles.blade.php`
Layer: View  
MVC role: the most important front-end addition; it hosts the version reader UI  
Main model concepts shown in UI: `Version`, facsimiles, pagination sidecar, fallback comparison XHTML

##### Reader preference storage

| Function | Description |
| --- | --- |
| `escapeHtml` | Escapes dynamic text inserted into the reader UI. |
| `loadReaderFitPreference` | Reads saved fit mode preference from browser storage. |
| `readerEncodingStorageKey` | Builds the local-storage key for a version's chosen text encoding. |
| `normalizeReaderEncoding` | Normalizes encoding labels into accepted values. |
| `loadReaderEncodingPreference` | Reads the preferred encoding for one version. |
| `saveReaderEncodingPreference` | Persists the preferred encoding for one version. |
| `readerCropStorageKey` | Builds the local-storage key for per-image crop data. |
| `loadStoredReaderCrop` | Reads saved crop settings for one image. |
| `saveStoredReaderCrop` | Saves crop settings for one image. |
| `clearStoredReaderCrop` | Removes saved crop settings for one image. |

##### Reader state and display control

| Function | Description |
| --- | --- |
| `applyReaderEncodingControl` | Synchronizes the encoding selector with current reader state. |
| `updateReaderCardTitle` | Updates the reader panel title according to the current page/image. |
| `currentReaderImageName` | Returns the currently displayed image name. |
| `hideReaderCropOverlay` | Hides the crop editing overlay. |
| `updateReaderCropControls` | Updates crop-related buttons and controls. |
| `resetReaderImageInlineStyles` | Clears temporary inline styles applied to the image. |
| `applyReaderCropDisplay` | Applies the saved crop rectangle to the current image. |
| `syncReaderCropForCurrentPage` | Reloads crop information when the current page changes. |
| `applyReaderFitMode` | Applies the active image-fit mode to the reader. |
| `setReaderFitMode` | Switches the fit mode and stores it. |
| `normalizeReaderCode` | Normalizes page/image codes on the client side. |
| `describePaginationOrigin` | Explains whether pagination is real or guessed. |
| `buildReaderSummary` | Builds the reader summary text shown in the UI. |
| `resetReader` | Resets reader state to an initial placeholder state. |

##### Reader rendering and lazy loading

| Function | Description |
| --- | --- |
| `renderReaderThumbs` | Draws the thumbnail strip for available facsimiles/pages. |
| `scrollCurrentThumbIntoView` | Keeps the active thumbnail visible. |
| `buildReaderPages` | Builds normalized client-side page objects from server data. |
| `mergeReaderPage` | Merges a lazily loaded page into existing reader state. |
| `loadReaderPage` | Fetches one page from the backend reader API. |
| `renderReaderText` | Renders the textual content of the current page. |
| `renderReaderPage` | Renders one full page in the reader. |
| `renderReader` | Renders the whole reader payload returned by the backend. |
| `loadReader` | Loads the reader for a selected version. |

##### Crop editing helpers

| Function | Description |
| --- | --- |
| `updateCropRect` | Updates the temporary crop rectangle while the user edits it. |
| `clearCropDraft` | Clears the in-progress crop rectangle. |

#### `laravel/resources/views/components/main/medite.blade.php`
Layer: View  
MVC role: Medite error display in the browser

| Function | Description |
| --- | --- |
| `ensureMediteErrorModal` | Ensures the Medite error modal exists in the DOM. |
| `stringifyMediteError` | Converts an API error payload into readable text. |
| `pushValue` | Helper to collect error detail fragments cleanly. |
| `showMediteError` | Displays a formatted Medite error to the user. |

#### `laravel/resources/views/components/main/versions.blade.php`
Layer: View  
MVC role: versions list interaction

| Function | Description |
| --- | --- |
| `updateViewerRowSelection` | Updates selected-row styling in the versions table. |

#### `laravel/resources/views/pages/main.blade.php`
Layer: View / page composition  
MVC role: top-level admin-page wording and step transitions

| Function | Description |
| --- | --- |
| `authorHeadingPreposition` | Picks the right French wording around an author heading. |
| `dispatchEditorialStepChanged` | Emits a custom event when the editorial workflow step changes. |

### F. Tests and Test Helpers

#### `laravel/tests/Feature/ExampleTest.php`
Layer: Feature test  
MVC role: verifies request/response behavior

| Function | Description |
| --- | --- |
| `test_guest_is_redirected_to_the_login_form` | Checks that unauthenticated access is redirected to login. |

#### `laravel/tests/TestCase.php`
Layer: Test infrastructure  
MVC role: supports controller/model/view integration tests

| Function | Description |
| --- | --- |
| `setUp` | Prepares the shared test environment before each test. |
| `signInEditor` | Logs in a test user with editor-level permissions. |
| `signInAdmin` | Logs in a test admin user. |
| `grantAuthorEditPermission` | Grants one user edit permission on an author. |
| `grantWorkEditPermission` | Grants one user edit permission on a work. |
| `createEditableWork` | Creates an author/work pair already prepared for editing tests. |
| `prepareVarianceFilesystem` | Creates the required storage and legacy-mirror test directories. |
| `writeVersionXml` | Writes a TEI/XML fixture for a version. |
| `writeComparisonArtifacts` | Writes comparison fixture files such as XHTML outputs. |
| `writeFacsimilePair` | Writes a facsimile image and its thumbnail for tests. |

## 4. Quick Reading Guide for Review

If the goal is code review rather than architecture learning, the highest-value order is:

1. `VersionController.php`
2. `PageMarkerService.php`
3. `resources/views/components/main/facsimiles.blade.php`
4. `ComparisonController.php` and `public/js/comparisons.js`
5. `public/js/work_selector.js`
6. factories and tests

Reason:
- the controller and service additions change real application behavior
- the facsimile/reader view contains the client-side half of that same behavior
- the remaining files are mostly supporting UI, test data, or test helpers
