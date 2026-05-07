# Laravel Current Code Map

Cette note cartographie l’état **actuel** du code Laravel dans Variance.

Elle remplace l’ancien document historique de cartographie partielle, qui suivait
un diff ancien précis. Celui-ci décrit le **code courant** du dossier `laravel/`.

## 1. Périmètre

Sont couverts ici :
- runtime PHP dans `laravel/app`
- routes dans `laravel/routes`
- logique front-end importante dans :
  - `laravel/public/js`
  - certains Blade avec script inline
- suites de tests dans `laravel/tests`

Ce n’est pas une documentation métier détaillée de chaque écran.  
C’est une **carte d’orientation** pour retrouver rapidement où vit chaque responsabilité.

## 2. Vue d’ensemble des couches

| Couche | Dossier | Rôle |
| --- | --- | --- |
| Helpers globaux | `laravel/app/Helpers` | URLs admin/legacy, utilitaires de slug |
| Commandes console | `laravel/app/Console/Commands` | maintenance, backups, import legacy, warmup, testbeds |
| Contrôleurs HTTP | `laravel/app/Http/Controllers` | API admin, pages, publication, import, santé, fac-similés |
| Middleware | `laravel/app/Http/Middleware` | auth, admin-only, maintenance mode |
| Jobs | `laravel/app/Jobs` | queues Laravel : `_lignes`, fac-similés, export, probes |
| Modèles | `laravel/app/Models` | auteurs, œuvres, versions, comparaisons, chapitres, permissions |
| Policies | `laravel/app/Policies` | droits d’édition |
| Services | `laravel/app/Services` | logique métier non HTTP |
| JS front-end | `laravel/public/js` | tables et sélecteurs riches |
| Blade avec logique inline | `laravel/resources/views` | composants admin avec JS embarqué |
| Tests | `laravel/tests` | workflow, intégration, helpers de test |

## 3. Routes

Le code actuel utilise **deux** fichiers de routes :

- [web.php](/Users/jganivet/Développement/variance2/laravel/routes/web.php:1)
  - pages Blade
  - une grande partie des endpoints admin
- [api.php](/Users/jganivet/Développement/variance2/laravel/routes/api.php:1)
  - fac-similés
  - publication
  - page markers / sidecars
  - manifests

Le nom `api.php` ne signifie pas que tout le backend JSON est là.  
Une partie importante des endpoints JSON reste déclarée dans `web.php`.

## 4. Helpers globaux

### `laravel/app/Helpers/helpers.php`

Fonctions :
- `makeUniqueSlug`
- `admin_base_prefix`
- `admin_path`
- `admin_url`
- `admin_asset`
- `legacy_url`

Usage principal :
- génération cohérente des URLs Laravel admin et miroir legacy

## 5. Commandes console

### Maintenance / exploitation

#### `AdminMaintenanceAnnounce.php`
- `handle`
- `resolveDate`

#### `AdminMaintenanceAnnouncementClear.php`
- `handle`

#### `AdminMaintenanceOn.php`
- `handle`
- `resolveUntil`

#### `AdminMaintenanceOff.php`
- `handle`

#### `BackupDatabase.php`
- `handle`
- `resolveOutputDir`
- `backupFileName`
- `resolveDumpBinary`
- `findExecutable`
- `buildDumpCommand`
- `streamDumpToGzip`
- `pruneExpiredBackups`
- `formatBytes`
- `sanitizedProcessEnv`

#### `WarmReaderCache.php`
- `handle`

#### `WriteSchedulerHeartbeat.php`
- `handle`

### Import / migration / backfill

#### `ImportLegacy.php`
- `handle`
- `parseDump`
- `parseValues`
- `normalizeValue`
- `decodeString`
- `importAuthors`
- `importWorks`
- `importVersions`
- `importComparisons`
- `importChapters`
- `ensureWorkStatuses`
- `writeWorkMap`
- `syncLegacyPdfs`

#### `ImportLegacyVersionTexts.php`
- `handle`
- `resolveLegacyTextPath`
- `relativePath`
- `readFileAsUtf8`
- `convertToUtf8`
- `preferMacRomanIfCleaner`
- `decodedTextNoiseScore`

#### `BackfillLegacyWorkShortTitles.php`
- `handle`
- `analyzeWork`
- `extractShortTitleCandidate`
- `normalizeShortTitle`
- `summarizeCandidates`
- `summarizeVersionFolders`

#### `BackfillVersionTeiFromTxt.php`
- `handle`
- `resolveXmlIdentifierNumber`
- `readFileAsUtf8`
- `convertToUtf8`
- `preferMacRomanIfCleaner`
- `decodedTextNoiseScore`
- `isLikelyTextContent`
- `buildLegacyTxt2TeiXml`
- `buildTeiHeaderXml`
- `normalizeTxt2TeiCharacters`
- `collapseTxt2TeiSpacesAndTabs`

### Génération de bancs de test

#### `SeedPaginationEditorTestbed.php`
- `handle`
- `cleanupExistingTestbed`
- `upsertVersionWithFiles`
- `writeVersionFiles`
- `writeComparisonScaffold`
- `buildTei`
- `buildXhtmlFromText`
- `emptyXhtml`
- `mirrorToPublic`
- `mirrorToLegacy`
- `deleteVersionFiles`
- `deleteWorkUploadsTree`
- `comparisonFolder`
- `sourceText`
- `targetText`

#### `SeedPaginationMarkerPlacementTestbed.php`
- `handle`
- `cleanupExistingTestbed`
- `upsertVersionWithFiles`
- `writeVersionFiles`
- `writeComparisonScaffold`
- `writeFacsimiles`
- `buildSolidPng`
- `pngChunk`
- `palette`
- `buildTei`
- `buildXhtmlFromText`
- `emptyXhtml`
- `mirrorToPublic`
- `mirrorToLegacy`
- `deleteVersionFiles`
- `deleteWorkUploadsTree`
- `sourceText`
- `targetText`

## 6. Contrôleurs HTTP

### Auth / compte / administration

#### `AccountController.php`
- `editPassword`
- `updatePassword`

#### `Auth/LoginController.php`
- `showLoginForm`
- `login`
- `logout`

#### `Auth/RegisterController.php`
- `showRegistrationForm`
- `register`

#### `UserManagementController.php`
- `index`
- `store`
- `update`
- `destroy`

#### `TaskMonitorController.php`
- `index`

### Auteurs / œuvres / médias

#### `AuthorController.php`
- `store`
- `getWorksByAuthor`
- `index`
- `update`
- `destroy`

#### `WorkController.php`
- `canEdit`
- `store`
- `getDescription`
- `updateDescription`
- `getStatus`
- `updateStatus`
- `show`
- `update`
- `destroy`
- `forbidIfCannotEdit`
- `forbidIfCannotEditStepOne`

#### `MediaController.php`
- `index`
- `store`
- `destroy`
- `forbidIfCannotEdit`
- `forbidIfCannotEditStepOne`
- `mirrorToLegacy`
- `deleteLegacyImage`
- `mirrorPdfToLegacy`
- `deleteLegacyPdf`
- `safeDiskSize`
- `safeDiskMimeType`

### Versions / pagination / lecteur / manifests

#### `VersionController.php`

Méthodes publiques majeures :
- `index`
- `buildVersionsList`
- `textLength`
- `store`
- `update`
- `togglePaginationDone`
- `destroy`
- `cancelFacsimiles`
- `cancelLignes`
- `deleteLignesFile`
- `manifestComparisons`
- `updateManifestImages`
- `downloadText`
- `downloadXml`
- `applyPageMarkers`
- `uploadLignes`
- `pageMarkersProgress`
- `downloadLignes`
- `paginationInfo`
- `readerData`
- `rebuildReaderData`
- `buildReaderResponsePayload`
- `readerProgress`
- `readerPage`
- `convertTextToUtf8`
- `clearPageMarkers`
- `createPaginationFromPb`
- `mergePaginationFromPb`
- `warmReaderCache`
- `clearReaderCache`
- `toggleIgnoredPage`

Helpers et logique interne notables :
- lecture/normalisation TXT :
  - `readFileAsUtf8`
  - `normalizeSourceEncodingHint`
  - `convertToUtf8`
  - `preferMacRomanIfCleaner`
- génération TEI :
  - `buildLegacyTxt2TeiXml`
  - `buildTeiHeaderXml`
- fac-similés :
  - `facsimileStatus`
  - `readerFacsimiles`
  - `collectReaderFacsimiles`
  - `purgeFacsimileStorage`
  - `deleteVersionPrivateArtifacts`
- lecteur synchronisé :
  - `readerDataset`
  - `buildReaderDataset`
  - `buildReaderDatasetBundle`
  - `assembleReaderDatasetPayload`
  - `readerPagePlans`
  - `readerGuessedPagePlans`
  - `materializeReaderPage`
  - `readerImageCode`
- manifests :
  - `publishManifestsForVersion`
  - `formatManifestComparison`
  - `manifestRelativePath`
  - `readManifestMetadata`
  - `resolveManifestEntries`
  - `buildManifestEntryFromName`
- permissions :
  - `assertWorkEditable`
  - `assertVersionEditable`
  - `assertVersionTextNormalizationAllowed`
  - `assertComparisonOwnership`

#### `FacsimileController.php`
- `__construct`
- `store`
- `cancelUpload`
- `refreshComparisonMarkers`
- `index`
- `freeSpace`
- `humanReadableSize`
- `backupRootPath`
- `backupPreviousSeries`
- `restorePreviousSeries`
- `clearBackupSeries`
- `cancelMarkerPath`
- `setCancelMarker`
- `clearCancelMarker`
- `hasCancelMarker`
- `detectSourcePageCount`

#### `EditorController.php`
- `__construct`
- `versionEditor`
- `versionEditorDocument`
- `versionUpdate`
- `comparisonEditor`
- `shouldLazyLoadVersionEditor`
- `loadVersionEditorXml`
- `comparisonUpdate`
- `removeTransformation`
- `comparisonConsistency`
- `resolveComparisonComponentPath`
- `swapRefPrefix`
- `removeTransformationListItems`
- `removeElementsByIds`
- `parseXmlDocument`
- `loadXmlAttempt`
- `formatLibxmlErrors`
- `collectDomIds`
- `collectAnchorFragmentRefs`
- `getPublicationInfo`
- `parseImagesData`
- `detectEncoding`
- `assertComparisonOwnership`

### Comparaisons / Medite / publication / chapitres / santé

#### `ComparisonController.php`
- `__construct`
- `getByWork`
- `details`
- `updateComments`
- `enrichComparison`
- `countMediteComponentEntries`
- `countFileOccurrences`
- `publicationCounts`
- `publicMenu`
- `reorder`
- `destroy`
- `deleteTransientComparisonInputs`
- `pruneComparisonParents`
- `exportPublishedLegacy`
- `queueLegacyExport`
- `exportStatus`
- `downloadLegacyExport`
- `applyPageMarkers`
- `buildPaginationFromXhtml`
- `pageMarkersProgress`
- `restorePageMarkers`
- `cancelPageMarkers`
- `flushPendingPaginationJobs`
- `resolvePublicationPaths`
- `exportBundlePayload`
- `manifestStatusForComparison`
- `resolveManifestFolders`
- `assertComparisonEditable`
- `assertComparisonUnpublished`
- `scopeComparisonsToUser`
- `assertComparisonOwnership`
- `showManifest`

#### `MediteController.php`
- `__construct`
- `createComparison`
- `runMedite`
- `taskStatus`
- `stageMediteInput`
- `resolveVersionXmlPath`
- `nextFolderAndNumber`
- `assertVersionsEditable`
- `hasMediteXmlInput`
- `resolveCreatorId`
- `assertComparisonOwnership`

#### `PublishController.php`
- `__construct`
- `publish`
- `unpublish`
- `resolvePaths`
- `mirrorToLegacy`
- `sanitizeComponent`
- `deletePublishedFiles`
- `publishFacsimilesForComparison`
- `publishFacsimilesForVersion`
- `legacyFacsimilesAreSynced`
- `ensureLegacyDirectoryExists`
- `pathsShareSameEntry`
- `facsimilePaths`
- `insertDefaultMarkers`
- `insertDefaultMarkerForRole`
- `findFirstFacsimileImageNumber`
- `buildDefaultMarker`
- `mirrorDraftComparisonToLegacy`
- `legacyDraftComparisonAlreadySynced`
- `draftMirrorFiles`
- `publishManifests`
- `removeManifests`
- `loadExistingManifestEntries`
- `assertComparisonEditable`
- `assertComparisonOwnership`
- `collectManifestEntries`

#### `ChaptersController.php`
- `__construct`
- `targets`
- `preview`
- `show`
- `commit`
- `assertWorkEditable`
- `assertWorkChapterReadable`
- `previewCacheKey`

#### `HealthController.php`
- `__construct`
- `index`
- `page`
- `buildReport`
- `freeSpace`
- `formatBytes`
- `markWarning`
- `markCritical`
- `statusToHttpCode`
- `isCriticalHttpTarget`
- `isExpectedAdminMaintenanceResponse`
- `resolveFailedWindowKey`
- `failedWindowSeconds`
- `failedWindowMap`
- `resolveHttpTargets`
- `checkPaths`
- `runMediteProbe`
- `deriveWorkerStatus`
- `runWorkerProbe`
- `readSchedulerHeartbeat`
- `mergeWorkerHeartbeat`
- `resolveMigrationStatus`
- `resolveGitMetadata`
- `resolveGitShaFromWorkTree`
- `resolveBuildRevision`
- `resolveGitShaFromGitDir`
- `lookupPackedRef`
- `normalizeGitSha`
- `resolveEnvValue`

#### `Controller.php`
- `audit`

## 7. Middleware

### `AdminMaintenanceMode.php`
- `__construct`
- `handle`
- `isExcluded`
- `retryAfterHeader`

### `Authenticate.php`
- `redirectTo`

### `EnsureAdmin.php`
- `handle`

## 8. Jobs

### `ApplyLignesJob.php`
- `__construct`
- `uniqueId`
- `handle`
- `performPagination`
- `failed`

### `GenerateLegacyExportJob.php`
- `__construct`
- `uniqueId`
- `handle`
- `failed`

### `HealthcheckProbeJob.php`
- `handle`

### `InjectComparisonPaginationJob.php`
- `__construct`
- `uniqueId`
- `handle`
- `failed`
- `ensureManifests`
- `loadExistingManifestEntries`
- `mirrorToLegacy`

### `ProcessFacsimileImage.php`
- `__construct`
- `handle`
- `isCancelled`
- `readSourceImage`

## 9. Modèles

### `Author.php`
- `boot`
- `works`
- `status`
- `permissions`

### `Work.php`
- `boot`
- `workStatus`
- `versions`
- `author`

### `Version.php`
- `work`
- `comparisonsAsSource`
- `comparisonsAsTarget`
- `status`
- `paginationDoneBy`
- `getXMLFilePath`
- `getFileSizeAttribute`
- `getFileSizeFormattedAttribute`
- `collectManifestEntries`
- `getIgnoredPages`
- `toggleIgnoredPage`

### `Comparison.php`
- `sourceVersion`
- `targetVersion`
- `status`
- `creator`
- `getSourceFilePath`
- `getTargetFilePath`
- `getFilePath`

### `Chapter.php`
- `parent`
- `children`
- `scopeForFolder`

### `Permission.php`
- `user`
- `author`
- `work`

### `User.php`
- `createdAuthors`
- `permissions`
- `comparisons`
- `isResearcher`
- `editableWorks`
- `editableWorksForAuthor`
- `editableAuthors`
- `getDisplayNameAttribute`

### Statut et policies

#### `VersionStatus.php`
- `version`

#### `ComparisonStatus.php`
- `comparison`

#### `WorkStatus.php`
- `work`

#### `WorkPolicy.php`
- `edit`

## 10. Services

### `AdminMaintenanceMode.php`
- `currentState`
- `activate`
- `currentAnnouncement`
- `announce`
- `deactivate`
- `clearAnnouncement`
- `isEnabled`
- `allowsAdminBypass`
- `shouldBypassFor`
- `publicPayload`
- `disabledState`
- `disabledAnnouncement`
- `normalizeDate`
- `defaultMessage`
- `defaultAnnouncementMessage`
- `statePath`
- `announcementPath`
- `readPersistedState`
- `writePersistedState`
- `readJsonFile`

### `AuditLogService.php`
- `record`

### `ChapterImportService.php`
- `parseWorkbook`
- `buildPreview`
- `resolveSecondWorksheet`
- `loadSharedStrings`
- `loadWorksheetRows`
- `columnIndexFromCellReference`

### `FilesystemCleanupService.php`
- `pruneEmptyDirectories`
- `isDirectoryEmpty`
- `normalizePath`

### `LegacyExportService.php`
- `getSnapshot`
- `markQueued`
- `markRunning`
- `markFailed`
- `createExportForComparison`
- `absolutePathFromSnapshot`
- `deleteExportArtifacts`
- `emptySnapshot`
- `statusRelativePath`
- `exportDirRelativePath`
- `legacyStatusRelativePath`
- `legacyExportDirRelativePath`
- `storeSnapshot`
- `resolvePublishedVersionFolder`
- `resolvePublishedComparisonFolder`
- `resolvePublishedComparisonExport`
- `resolvePublicationPaths`
- `addManifestedFacsimilesToZip`
- `normalizeManifestAssetPath`
- `addDirectoryToZip`

### `PageMarkerService.php`

Service central de pagination, `_lignes`, sidecars, injection de balises et restauration.

Fonctions majeures :
- import `_lignes` et sidecar :
  - `applyLignesToVersion`
  - `generatePaginationSidecar`
  - `loadPaginationSidecar`
  - `parseLignesFile`
- comparaison / XHTML :
  - `applySidecarToComparison`
  - `applySidecarToComparisonRoleOnly`
  - `createSidecarFromComparisonOutputs`
  - `extractPbMarkersFromHtml`
  - `extractRuntimeMarkersFromComparisonHtml`
- version editor / TEI :
  - `buildVersionEditorXml`
  - `materializeCanonicalVersionXml`
  - `readVersionXml`
  - `versionEditorImageMap`
  - `resolveVersionXmlPath`
- balises `<pb>` :
  - `createSidecarFromPb`
  - `mergeSidecarFromPb`
  - `syncSidecarWithPb`
  - `extractPbMarkers`
- insertion / restauration :
  - `insertMarkers`
  - `insertMarkersFromOffsets`
  - `insertPbTagsFromOffsets`
  - `ensureOriginalBackups`
  - `restoreOriginalComparisonOutputs`
- résolution d’ancres :
  - `normalizeImageCode`
  - `resolveMarkerIndex`
  - `refineResolvedMarkerIndex`
  - `buildIndexedPlaintext`
  - `findMatch`
  - `findMatchWindow`
  - `phraseVariants`
- progression / annulation :
  - `markQueued`
  - `markFailed`
  - `markComparisonQueued`
  - `markComparisonFailed`
  - `markCancelled`
  - `clearCancellation`
  - `resetProgress`
  - `isCancelled`
  - `initProgress`
  - `progressTick`
  - `finishProgress`
  - `writeProgress`
  - `getProgressSnapshot`
  - `getComparisonProgressSnapshot`
- utilitaires de stockage :
  - `getLignesInfo`
  - `getPaginationInfo`
  - `getPaginationSidecar`
  - `getStoredLignesAbsolutePath`
  - `lignesRelativePath`
  - `paginationRelativePath`
  - `deleteLignesArtifacts`
  - `hasLignesFile`
  - `hasPaginationSidecar`
  - `isPaginationSidecarValid`

## 11. Front-end JavaScript principal

### `laravel/public/js/work_selector.js`

Responsabilité :
- sélection auteur / œuvre
- historique récent
- synchronisation URL et dropdowns

Fonctions majeures :
- `readHistory`, `writeHistory`, `pushHistory`
- `readLastSelection`, `writeLastSelection`
- `dispatchWorkSelected`
- `syncDropdownButton`
- `rebuildDropdownMenu`
- `syncAuthorDropdown`
- `syncWorkDropdown`
- `syncAddWorkButtonState`
- `loadAuthors`
- `loadWorks`
- `reflectSelectionInUrl`

### `laravel/public/js/comparisons.js`

Responsabilité :
- table des comparaisons
- publication
- export
- réordonnancement
- commentaires
- pagination de comparaison
- accès au panneau chapitres

Fonctions majeures :
- `initComparisonsTable`
- `loadComparisons`
- `loadComparisonDetails`
- `buildComparisonRow`
- `refreshRunningComparisonIndicators`
- `renderPublishStatusHtml`
- `renderManifestBadge`
- `updateManifest`
- `renderMediteParams`
- `renderComparisonDataSummary`
- `triggerComparisonPagination`
- `buildSidecarFromXhtml`
- `restoreComparisonPagination`
- `cancelComparisonPagination`
- `openCommentModal`
- `openChaptersPanel`
- `moveComparisonRowLocally`
- `refreshComparisonReorderButtons`
- `refreshExportStatus`
- `renderExportAction`

## 12. Blade avec logique inline importante

### `components/main/versions.blade.php`

Responsabilité :
- table des versions
- upload fac-similés
- upload `_lignes`
- polling de progression
- actions pagination / suppression / édition

Fonctions majeures :
- `requestTextLength`
- `requestFacsimileProgress`
- `ensureFacsimilePolling`
- `requestLignesProgress`
- `ensureLignesPolling`
- `renderFacsimileStatus`
- `renderLignesStatus`
- `togglePaginationDone`
- `openFacsimileUploadModal`
- `collectSelectedImages`
- `estimateFacsimilePages`
- `estimateTiffPages`
- `cancelCurrentFacsimileUpload`
- `checkFacsimileSpace`
- `purgeFacsimiles`
- `uploadLignesFile`
- `clearVersionPageMarkers`
- `createPaginationFromPb`
- `deleteLignesFile`
- `cancelLignesProcessing`
- `fetchVersions`
- `updateVersionName`
- `doDeleteVersion`

### `components/main/facsimiles.blade.php`

Responsabilité :
- galerie de fac-similés
- gestion des manifests
- lecteur synchronisé
- préférences locales de rendu, crop, fit mode

Fonctions majeures :
- `loadGallery`
- `renderGallery`
- `loadReader`
- `loadReaderPage`
- `renderReader`
- `renderReaderPage`
- `renderReaderText`
- `buildReaderPages`
- `applyReaderFitMode`
- `setReaderFitMode`
- `applyReaderCropDisplay`
- `saveManifestSelection`
- `loadManifestOptions`
- `applyManifestOption`
- `processFacsimileSelection`

### `components/main/chapters.blade.php`

Responsabilité :
- chargement des cibles chapitres
- prévisualisation import XLSX
- consultation legacy read-only

Fonctions majeures :
- `loadTargets`
- `renderTargets`
- `selectComparisonTarget`
- `renderPreview`
- `renderReadOnlyRows`
- `loadReadOnlyRows`
- `runPreview`
- `syncPreviewAvailability`

### Autres blades logiques

#### `components/main/media.blade.php`
- upload média œuvre, compression image, PDF, preview

#### `components/main/medite.blade.php`
- formulaire Medite, erreurs, polling de tâches

#### `components/main/description.blade.php`
- édition/sauvegarde description

#### `components/main/status.blade.php`
- statut éditorial œuvre

#### `pages/main.blade.php`
- orchestration des étapes de l’interface admin
- historique récent
- messages d’accueil et d’état

#### `layouts/app.blade.php`
- `withBasePath`
- `withBaseUrl`
- interception `fetch`
- menu latéral et indicateurs globaux

## 13. Tests actuels

### Infrastructure commune

#### `tests/TestCase.php`
- `setUp`
- `signInEditor`
- `signInAdmin`
- `grantAuthorEditPermission`
- `grantWorkEditPermission`
- `createEditableWork`
- `prepareVarianceFilesystem`
- `ensureDirectoryExistsWhenWritable`
- `cleanDirectoryIfPresent`
- `writeVersionXml`
- `writeComparisonArtifacts`
- `writeFacsimilePair`

### Feature tests workflow

#### `AdminMaintenanceModeTest.php`
- maintenance splash
- bypass admin
- annonce planifiée
- health privé/public
- migrations pending
- checks legacy paths

#### `ChaptersImportWorkflowTest.php`
- listing cibles chapitres
- preview/commit import
- lecture seule legacy

#### `ComparisonCommentsWorkflowTest.php`
- commentaires de comparaison
- audit log
- nettoyage fichiers lors de suppression

#### `ComparisonWorkflowTest.php`
- création comparaison Medite
- task status
- filtrage visibilité des comparaisons

#### `DatabaseBackupCommandTest.php`
- dump compressé
- rétention

#### `FacsimileWorkflowTest.php`
- upload fac-similés
- rejet legacy

#### `PublicationWorkflowTest.php`
- publication / dépublication
- export zip
- manifests
- fac-similés same mount / synced / warnings
- audit log

#### `VersionEditorCacheTest.php`
- cache éditeur version

#### `VersionEditorLoadingTest.php`
- lazy loading document XML
- endpoint document
- fallback inline

#### `VersionImportWorkflowTest.php`
- import version
- index versions
- pagination done
- suppression artefacts privés

#### `VersionReaderWorkflowTest.php`
- auth des endpoints lecteur
- payload lecteur
- rebuild
- fallback XHTML
- alignement fac-similés
- conversion UTF-8
- invalidation cache lecteur

#### `WorkManagementTest.php`
- création œuvre
- restrictions legacy
- suppression bloquée auteur/œuvre

### Tests simples

#### `Feature/ExampleTest.php`
- redirection invité vers login

#### `Unit/ExampleTest.php`
- test trivial d’exemple

## 14. Guide de lecture recommandé

Pour comprendre rapidement le Laravel actuel, lire dans cet ordre :

1. `routes/web.php` et `routes/api.php`
2. `VersionController.php`
3. `ComparisonController.php`
4. `PublishController.php`
5. `FacsimileController.php`
6. `PageMarkerService.php`
7. `resources/views/components/main/versions.blade.php`
8. `resources/views/components/main/facsimiles.blade.php`
9. `resources/views/components/main/comparisons.blade.php` + `public/js/comparisons.js`
10. `tests/Feature/Workflow/*`

## 15. Conclusion

Le Laravel actuel couvre désormais :
- gestion éditoriale auteurs / œuvres / versions
- fac-similés et manifests
- pagination `_lignes` et sidecars
- lecteur synchronisé
- comparaisons, publication, export
- maintenance, santé et exploitation
- audit minimal et backups

Pour la cartographie actuelle, utiliser ce document comme point d’entrée.
