<?php

namespace App\Http\Controllers;

use App\Jobs\ApplyLignesJob;
use App\Models\Comparison;
use App\Models\Version;
use App\Models\Work;
use App\Services\FilesystemCleanupService;
use App\Services\PageMarkerService;
use App\Services\VersionReaderService;
use App\Services\VersionTextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VersionController extends Controller
{
    public function __construct(
        private PageMarkerService $pageMarkerService,
        private FilesystemCleanupService $filesystemCleanupService,
        private VersionReaderService $versionReaderService,
        private VersionTextService $versionTextService,
    ) {}

    /* ───────────────────────── PUBLIC ENDPOINTS ───────────────────────── */

    /** List versions for a given work */
    public function index(Request $request)
    {
        $workId = $request->query('work_id');
        if (! $workId) {
            return response()->json(['error' => 'work_id is required'], 400);
        }

        $forceFresh = $request->boolean('fresh');
        $cacheKey = "versions:index:work:{$workId}";
        $cacheTtl = now()->addSeconds(8);

        $versions = $forceFresh
            ? $this->buildVersionsList((int) $workId)
            : Cache::remember($cacheKey, $cacheTtl, fn () => $this->buildVersionsList((int) $workId));

        return response()->json($versions, 200);
    }

    private function buildVersionsList(int $workId): array
    {
        $versions = Version::with(['work.author', 'paginationDoneBy'])
            ->where('work_id', $workId)
            ->get();

        $versionIds = $versions->pluck('id')->all();
        $usageByVersion = [];
        if (! empty($versionIds)) {
            Comparison::query()
                ->where(function ($query) use ($versionIds) {
                    $query->whereIn('source_id', $versionIds)
                        ->orWhereIn('target_id', $versionIds);
                })
                ->get(['id', 'source_id', 'target_id'])
                ->each(function (Comparison $comparison) use (&$usageByVersion): void {
                    foreach (array_unique(array_filter([
                        $comparison->source_id,
                        $comparison->target_id,
                    ])) as $versionId) {
                        $usageByVersion[(int) $versionId] ??= [];
                        $usageByVersion[(int) $versionId][] = (int) $comparison->id;
                    }
                });
        }

        return $versions
            ->map(function (Version $version) use ($usageByVersion) {
                $paginationInfo = $this->pageMarkerService->getPaginationInfo($version->id);
                $pageMarkerProgress = $version->is_legacy
                    ? null
                    : $this->pageMarkerService->getProgressSnapshot($version->id);
                $textLength = null; // lazy-loaded via /api/versions/{id}/text-length
                $comparisonIds = $usageByVersion[$version->id] ?? [];

                return [
                    'id' => $version->id,
                    'name' => $version->name,
                    'folder' => $version->folder,
                    'work_id' => $version->work_id,
                    'is_legacy' => (bool) $version->is_legacy,
                    'is_in_use' => ! empty($comparisonIds),
                    'in_use_comparison_count' => count($comparisonIds),
                    'in_use_comparison_ids' => $comparisonIds,
                    'pagination_done' => (bool) $version->pagination_done,
                    'pagination_done_at' => $version->pagination_done_at?->getTimestamp(),
                    'pagination_done_by' => $version->pagination_done_by,
                    'pagination_done_by_name' => $version->paginationDoneBy?->name,
                    'xml_available' => is_file($version->getXMLFilePath()),
                    'xml_url' => admin_url("versions/{$version->id}/download-xml"),
                    'text_available' => is_file(storage_path("app/public/uploads/versions/{$version->folder}.txt")),
                    'text_url' => admin_url("versions/{$version->id}/download"),
                    'text_length' => $textLength,
                    'facsimiles' => null, // lazy-loaded via /api/versions/{id}/facsimiles/progress
                    'page_marker_progress' => $pageMarkerProgress,
                    'lignes' => null, // loaded on-demand by row actions
                    'pagination' => $paginationInfo,
                ];
            })
            ->values()
            ->all();
    }

    public function textLength(Version $version): JsonResponse
    {
        $path = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        if (! is_file($path)) {
            return response()->json([
                'version_id' => $version->id,
                'text_available' => false,
                'text_length' => null,
            ], 200);
        }

        $mtime = @filemtime($path) ?: 0;
        $cacheKey = "versions:text_length:{$version->id}:{$mtime}";

        $length = Cache::remember($cacheKey, now()->addHours(6), function () use ($path) {
            $contents = File::get($path);

            return mb_strlen($contents, 'UTF-8');
        });

        return response()->json([
            'version_id' => $version->id,
            'text_available' => true,
            'text_length' => $length,
        ], 200);
    }

    /** Upload → save .txt untouched → generate TEI (UTF‑8 LF) */
    public function store(Request $request)
    {
        /* 1. Validate */
        $validated = $request->validate([
            'work_id' => 'required|exists:works,id',
            'name' => 'required|string|max:100',
            'versionFile' => 'required|file|max:8192',
        ]);

        /* 2. Context */
        $work = Work::findOrFail($validated['work_id']);
        $this->assertWorkEditable($work);
        $shortTitle = $work->short_title;
        $nextNumber = Version::where('work_id', $work->id)->count() + 1;
        $baseName = "{$nextNumber}{$shortTitle}";
        $authorFolder = $work->author->folder ?? null;
        $workFolder = $work->folder ?? null;
        $folderPath = 'uploads/versions'; // storage/app/public

        if ($authorFolder && $workFolder) {
            $removedStaleFacsimiles = $this->purgeFacsimileStorageByFolders(
                $authorFolder,
                $workFolder,
                $baseName,
                includePublished: true
            );

            if ($removedStaleFacsimiles) {
                Log::info('Removed stale facsimile artifacts before version import', [
                    'work_id' => $work->id,
                    'author_folder' => $authorFolder,
                    'work_folder' => $workFolder,
                    'version_folder' => $baseName,
                ]);
            }
        }

        /* 3. Persist raw .txt (no conversion) */
        $txtFilename = "{$baseName}.txt";
        $txtStoragePath = "{$folderPath}/{$txtFilename}";
        $request->file('versionFile')->storeAs($folderPath, $txtFilename, 'public');

        /* 4. Read & normalise */
        $fullTxt = storage_path("app/public/{$txtStoragePath}");
        $utf8 = $this->versionTextService->readFileAsUtf8(
            $fullTxt,
            $request->input('original_encoding'),
            false
        );
        if (! $this->versionTextService->isLikelyTextContent($utf8)) {
            throw ValidationException::withMessages([
                'versionFile' => 'Le fichier importé ne semble pas être un texte lisible.',
            ]);
        }
        $teiTitle = $work->title ?: $validated['name'];
        $teiAuthor = $work->author?->name ?: '';
        $tei = $this->versionTextService->buildLegacyTxt2TeiXml($utf8, $teiTitle, $nextNumber, $teiAuthor, $validated['name']);

        /* 7. Save .xml */
        Storage::disk('public')->put("{$folderPath}/{$baseName}.xml", $tei);

        /* 8. DB row */
        $version = Version::create([
            'work_id' => $work->id,
            'name' => $validated['name'],
            'folder' => $baseName,
        ]);
        Cache::forget("versions:index:work:{$work->id}");

        return response()->json([
            'message' => 'Version uploaded successfully!',
            'version' => $version,
        ], 201);
    }

    /** Update name */
    public function update(Request $req, $id)
    {
        $version = Version::findOrFail($id);
        $this->assertVersionEditable($version);
        $version->update($req->validate(['name' => 'required|string|max:100']));
        Cache::forget("versions:index:work:{$version->work_id}");

        return response()->json($version, 200);
    }

    public function togglePaginationDone(Request $request, Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);
        $data = $request->validate([
            'done' => 'required|boolean',
        ]);

        $done = (bool) $data['done'];

        $version->update([
            'pagination_done' => $done,
            'pagination_done_at' => $done ? now() : null,
            'pagination_done_by' => $done ? (auth()->id() ?: null) : null,
        ]);

        $version->loadMissing('paginationDoneBy');
        Cache::forget("versions:index:work:{$version->work_id}");

        return response()->json([
            'status' => 'ok',
            'version_id' => $version->id,
            'pagination_done' => $version->pagination_done,
            'pagination_done_at' => $version->pagination_done_at?->getTimestamp(),
            'pagination_done_by' => $version->pagination_done_by,
            'pagination_done_by_name' => $version->paginationDoneBy?->name,
        ]);
    }

    /** Delete version; tolerate missing files */
    public function destroy($id)
    {
        $version = Version::with('work.author')->findOrFail($id);
        $this->assertVersionEditable($version);

        // Prevent deletion if used in a comparison
        $inUse = Comparison::where('source_id', $version->id)
            ->orWhere('target_id', $version->id)
            ->exists();
        if ($inUse) {
            return response()->json(['error' => 'Impossible de supprimer : version utilisée.'], 400);
        }

        $disk = Storage::disk('public');
        $base = $version->folder;
        $paths = [
            "uploads/versions/{$base}.xml",
            "uploads/versions/{$base}.txt",
            "uploads/versions/{$base}.medite.txt",
        ];

        $missing = [];
        foreach ($paths as $p) {
            if ($disk->exists($p)) {
                $disk->delete($p);
            } else {
                $missing[] = $p;
            }
        }

        $facsimilesRemoved = $this->purgeFacsimileStorage($version, includePublished: true);
        $lignesRemoved = false;
        $paginationRemoved = false;
        $lignesPath = null;
        $paginationPath = null;
        try {
            $lignesPath = $this->pageMarkerService->lignesRelativePath($version->id);
            if (Storage::disk('local')->exists($lignesPath)) {
                $lignesRemoved = Storage::disk('local')->delete($lignesPath);
            }
            $paginationPath = $this->pageMarkerService->paginationRelativePath($version->id);
            if (Storage::disk('local')->exists($paginationPath)) {
                $paginationRemoved = Storage::disk('local')->delete($paginationPath);
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to delete _lignes file during version removal', [
                'version_id' => $version->id,
                'path' => $lignesPath ?? null,
                'pagination_path' => $paginationPath ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $this->deleteVersionPrivateArtifacts($version->id);

        // Remove DB record regardless of file presence
        $versionId = $version->id;
        $versionFolder = $version->folder;
        $workId = $version->work_id;
        $version->delete();
        Cache::forget("versions:index:work:{$version->work_id}");

        $this->audit('version.deleted', [
            'version_id' => $versionId,
            'version_folder' => $versionFolder,
            'work_id' => $workId,
            'missing_files' => $missing,
            'facsimiles_removed' => $facsimilesRemoved,
            'lignes_removed' => $lignesRemoved,
            'pagination_removed' => $paginationRemoved,
        ]);

        $message = 'Version supprimée avec succès';
        if ($missing) {
            $message .= ' — fichiers introuvables : '.implode(', ', $missing);
        }
        if ($facsimilesRemoved) {
            $message .= ' — fac-similés supprimés.';
        }
        if ($lignesRemoved) {
            $message .= ' — fichier _lignes supprimé.';
        }
        if ($paginationRemoved) {
            $message .= ' — sidecar pagination supprimé.';
        }

        return response()->json(['message' => $message]);
    }

    public function cancelFacsimiles(Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);
        $version->loadMissing('work.author');
        $this->setFacsimileCancelMarker($version->id);
        $removed = $this->purgeFacsimileStorage($version, includePublished: true);
        Cache::forget("versions:facsimiles:{$version->id}");
        Cache::forget("versions:index:work:{$version->work_id}");
        $facsimiles = $this->facsimileStatus($version);

        return response()->json([
            'status' => $removed ? 'reset' : 'noop',
            'message' => $removed
                ? 'Traitement fac-similé interrompu et fichiers supprimés.'
                : 'Aucun traitement en cours ou fichier à supprimer.',
            'facsimiles' => $facsimiles,
        ]);
    }

    public function cancelLignes(Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);
        $version->loadMissing('work.author');
        $this->pageMarkerService->markCancelled($version->id, 'Annulé par l\'utilisateur');
        $this->pageMarkerService->resetProgress($version->id);

        $info = $this->pageMarkerService->getLignesInfo($version->id);
        if ($info) {
            $info['url'] = admin_url("api/versions/{$version->id}/lignes");
        }
        $progress = ['status' => 'idle', 'version_id' => $version->id, 'updated_at' => time()];

        return response()->json([
            'status' => 'reset',
            'message' => 'Progression réinitialisée.',
            'lignes' => $info,
            'progress' => $progress,
        ]);
    }

    public function deleteLignesFile(Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);
        $progress = $this->pageMarkerService->getProgressSnapshot($version->id);
        $status = strtolower((string) ($progress['status'] ?? ''));
        if (in_array($status, ['queued', 'running'], true)) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Impossible de supprimer le fichier _lignes pendant un traitement en cours.',
                'progress' => $progress,
            ], 409);
        }

        $removed = $this->pageMarkerService->deleteLignesArtifacts($version->id);

        $info = $this->pageMarkerService->getLignesInfo($version->id);
        if ($info) {
            $info['url'] = admin_url("api/versions/{$version->id}/lignes");
        }

        $pagination = $this->pageMarkerService->getPaginationInfo($version->id);
        $updatedProgress = $this->pageMarkerService->getProgressSnapshot($version->id);

        $message = $removed['lignes_removed']
            ? 'Fichier _lignes supprimé.'
            : 'Aucun fichier _lignes à supprimer.';

        return response()->json([
            'status' => 'ok',
            'message' => $message,
            'lignes' => $info,
            'pagination' => $pagination,
            'progress' => $updatedProgress,
            'removed' => $removed,
        ]);
    }

    public function manifestComparisons(Version $version): JsonResponse
    {
        $version->loadMissing('work.author', 'work');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        if (! $authorFolder || ! $workFolder) {
            return response()->json([]);
        }

        $defaultEntries = $version->collectManifestEntries();
        $defaultNames = [];
        foreach ($defaultEntries as $entry) {
            $path = $entry['big'] ?? null;
            if ($path) {
                $name = basename($path);
                if ($name !== '') {
                    $defaultNames[] = $name;
                }
            }
        }
        $defaultNames = array_values(array_unique($defaultNames));

        $user = auth()->user();

        $comparisonsQuery = Comparison::with(['sourceVersion', 'targetVersion'])
            ->where(function ($query) use ($version) {
                $query->where('source_id', $version->id)
                    ->orWhere('target_id', $version->id);
            });

        if ($user && ! $user->is_admin) {
            $comparisonsQuery->where(function ($inner) use ($user) {
                $inner->where('created_by', $user->id)
                    ->orWhere('is_legacy', true);
            });
        }

        $comparisons = $comparisonsQuery
            ->orderByDesc('created_at')
            ->get();

        $payload = [];
        foreach ($comparisons as $comparison) {
            if ($comparison->source_id === $version->id) {
                $payload[] = $this->formatManifestComparison($version, $comparison, 'source', $defaultNames);
            }
            if ($comparison->target_id === $version->id) {
                $payload[] = $this->formatManifestComparison($version, $comparison, 'target', $defaultNames);
            }
        }

        return response()->json($payload);
    }

    public function updateManifestImages(Request $request, Version $version, Comparison $comparison): JsonResponse
    {
        $data = $request->validate([
            'role' => 'required|in:source,target',
            'images' => 'array',
            'images.*' => 'string',
        ]);

        $this->assertVersionEditable($version);
        $this->assertComparisonOwnership($comparison);

        $role = $data['role'];
        if (($role === 'source' && $comparison->source_id !== $version->id) ||
            ($role === 'target' && $comparison->target_id !== $version->id)) {
            return response()->json([
                'message' => 'Cette version n\'est pas associée à la comparaison pour le rôle sélectionné.',
            ], 422);
        }

        $version->loadMissing('work.author', 'work');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        if (! $authorFolder || ! $workFolder || ! $version->folder) {
            return response()->json([
                'message' => 'Impossible de déterminer l\'emplacement des fac-similés.',
            ], 422);
        }

        $relativePath = $this->manifestRelativePath($version, $comparison, $role);
        if (! $relativePath) {
            return response()->json([
                'message' => 'Chemin de manifeste invalide.',
            ], 422);
        }

        $versionEntries = $version->collectManifestEntries();
        $entriesByName = [];
        foreach ($versionEntries as $entry) {
            $path = $entry['big'] ?? null;
            if (! $path) {
                continue;
            }
            $name = basename($path);
            if ($name !== '') {
                $entriesByName[$name] = $entry;
            }
        }

        $selectedNames = [];
        $entries = [];
        $seen = [];

        foreach ($data['images'] ?? [] as $rawName) {
            $name = trim(basename($rawName));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            if (isset($entriesByName[$name])) {
                $entries[] = $entriesByName[$name];
                $selectedNames[] = $name;

                continue;
            }

            $entry = $this->buildManifestEntryFromName($version, $name);
            if ($entry) {
                $entries[] = $entry;
                $selectedNames[] = $name;
            }
        }

        Storage::disk('public')->makeDirectory(dirname($relativePath));
        $payload = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        Storage::disk('public')->put($relativePath, $payload);
        $this->mirrorToLegacy(dirname($relativePath), basename($relativePath), $payload);

        $metadata = $this->readManifestMetadata($version, $comparison, $role, $selectedNames);

        if (($metadata['count'] ?? 0) > 0) {
            $this->pageMarkerService->ensureDefaultMarkers($comparison);
        }

        return response()->json([
            'status' => 'ok',
            'selected' => $metadata['selected'],
            'count' => $metadata['count'],
            'exists' => $metadata['exists'],
            'file' => $metadata['file'],
            'updated_at' => $metadata['updated_at'],
            'inferred' => $metadata['inferred'],
        ]);
    }

    /** Download version text */
    public function downloadText(Version $version)
    {
        $path = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, "{$version->folder}.txt");
    }

    public function downloadXml(Version $version)
    {
        $path = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, "{$version->folder}.xml");
    }

    public function applyPageMarkers(Request $request, Version $version)
    {
        $this->assertVersionEditable($version);
        $validated = $request->validate([
            'lignes' => 'required|file|max:4096',
            'clear_existing' => 'sometimes|boolean',
            'replace_existing' => 'sometimes|boolean',
        ]);

        $tempPath = $request->file('lignes')->store('tmp/lignes', 'local');

        $progressFile = storage_path('app/tmp/pager/'.$version->id.'.json');
        if (is_file($progressFile)) {
            $payload = json_decode(@file_get_contents($progressFile), true);
            $status = $payload['status'] ?? null;
            if (in_array($status, ['queued', 'running'], true)) {
                Storage::disk('local')->delete($tempPath);

                return response()->json([
                    'status' => 'busy',
                    'message' => 'Une importation _lignes est déjà en cours pour cette version.',
                ], 409);
            }
        }

        $this->pageMarkerService->markQueued($version->id);

        try {
            ApplyLignesJob::dispatch(
                $version->id,
                $tempPath,
                true,
                $request->boolean('clear_existing', true),
                $request->boolean('replace_existing', true),
                null
            );
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($tempPath);
            throw $e;
        }

        return response()->json([
            'status' => 'queued',
            'version_id' => $version->id,
        ], 202);
    }

    public function uploadLignes(Request $request, Version $version)
    {
        $this->assertVersionEditable($version);
        $request->validate([
            'lignes' => 'required|file|max:4096',
        ]);

        $file = $request->file('lignes');
        $relative = $this->pageMarkerService->lignesRelativePath($version->id);
        Storage::disk('local')->putFileAs(dirname($relative), $file, basename($relative));
        $this->pageMarkerService->markQueued($version->id);

        try {
            ApplyLignesJob::dispatch(
                versionId: $version->id,
                storagePath: $relative,
                deleteAfter: false,
                clearExisting: true,
                replaceExisting: true,
                comparisonId: null
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de lancer le traitement des balises de pagination.',
            ], 500);
        }

        $info = $this->pageMarkerService->getLignesInfo($version->id);
        if ($info) {
            $info['url'] = admin_url("api/versions/{$version->id}/lignes");
        }
        $pagination = $this->pageMarkerService->getPaginationInfo($version->id);
        $progress = $this->pageMarkerService->getProgressSnapshot($version->id);

        return response()->json([
            'status' => 'queued',
            'message' => 'Import du fichier _lignes en cours…',
            'lignes' => $info,
            'pagination' => $pagination,
            'progress' => $progress,
        ], 202);
    }

    public function pageMarkersProgress(Version $version)
    {
        $progressFile = storage_path('app/tmp/pager/'.$version->id.'.json');
        if (! file_exists($progressFile)) {
            return response()->json(['status' => 'idle'], 200);
        }
        // Retry a couple times to avoid reading mid-write
        for ($i = 0; $i < 3; $i++) {
            $json = @file_get_contents($progressFile) ?: '';
            $payload = $json !== '' ? json_decode($json, true) : null;
            if (is_array($payload)) {
                $status = strtolower((string) ($payload['status'] ?? ''));
                $updatedAt = (int) ($payload['updated_at'] ?? 0);
                $staleThreshold = 180; // seconds
                if ($status === 'queued' && $updatedAt > 0 && (time() - $updatedAt) > $staleThreshold) {
                    $this->pageMarkerService->resetProgress($version->id);

                    return response()->json(['status' => 'idle', 'version_id' => $version->id, 'updated_at' => time()], 200);
                }

                return response()->json($payload, 200);
            }
            usleep(20000);
        }

        // If still invalid, signal running state so UI can keep polling
        return response()->json(['status' => 'running', 'version_id' => $version->id], 200);
    }

    public function downloadLignes(Version $version)
    {
        $relative = $this->pageMarkerService->lignesRelativePath($version->id);
        if (! Storage::disk('local')->exists($relative)) {
            abort(404, 'Fichier _lignes introuvable.');
        }

        $filename = $version->folder.'_lignes.txt';
        // Read via the Storage facade to avoid path resolution issues
        $stream = Storage::disk('local')->readStream($relative);
        if ($stream === false) {
            abort(404, 'Fichier _lignes introuvable.');
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function paginationInfo(Version $version)
    {
        $info = $this->pageMarkerService->getPaginationInfo($version->id);
        if (! $info) {
            return response()->json([
                'status' => 'missing',
                'version_id' => $version->id,
            ], 404);
        }

        return response()->json($info + ['version_id' => $version->id], 200);
    }

    public function readerData(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->versionTextService->normalizeSourceEncodingHint($request->query('encoding'));
        $requestedTextSource = $this->versionReaderService->normalizeReaderTextSourceHint($request->query('text_source'));
        $dataset = $this->versionReaderService->dataset($version, $requestedEncoding, $requestedTextSource);

        return response()->json($this->versionReaderService->responsePayload($version, $dataset), 200);
    }

    public function rebuildReaderData(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->versionTextService->normalizeSourceEncodingHint($request->input('encoding', $request->query('encoding')));
        $requestedTextSource = $this->versionReaderService->normalizeReaderTextSourceHint($request->input('text_source', $request->query('text_source')));
        $this->versionReaderService->clearCache($version);
        $dataset = $this->versionReaderService->dataset($version, $requestedEncoding, $requestedTextSource);

        return response()->json([
            'status' => 'ok',
            'message' => 'Lecteur reconstruit pour cette version.',
        ] + $this->versionReaderService->responsePayload($version, $dataset), 200);
    }

    public function readerProgress(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->versionTextService->normalizeSourceEncodingHint($request->query('encoding'));
        $requestedTextSource = $this->versionReaderService->normalizeReaderTextSourceHint($request->query('text_source'));

        return response()->json(
            $this->versionReaderService->progressPayload($version, $requestedEncoding, $requestedTextSource),
            200
        );
    }

    public function readerPage(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->versionTextService->normalizeSourceEncodingHint($request->query('encoding'));
        $requestedTextSource = $this->versionReaderService->normalizeReaderTextSourceHint($request->query('text_source'));
        $index = max(0, (int) $request->query('index', 0));
        $payload = $this->versionReaderService->pagePayload($version, $requestedEncoding, $requestedTextSource, $index);

        if (($payload['status'] ?? null) === 'missing') {
            return response()->json($payload, 404);
        }

        return response()->json($payload, 200);
    }

    public function convertTextToUtf8(Request $request, Version $version): JsonResponse
    {
        $this->assertVersionTextNormalizationAllowed($version);

        $validated = $request->validate([
            'encoding' => 'required|string|in:UTF-8,Windows-1252,ISO-8859-1,Mac Roman',
        ]);

        $textPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        if (! is_file($textPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fichier texte introuvable pour cette version.',
            ], 404);
        }

        $normalizedHint = $this->versionTextService->normalizeSourceEncodingHint($validated['encoding']);
        $utf8Text = $this->versionTextService->readFileAsUtf8($textPath, $normalizedHint);
        File::put($textPath, $utf8Text);

        clearstatcache(true, $textPath);
        $this->versionReaderService->clearCache($version);

        return response()->json([
            'status' => 'ok',
            'message' => "Fichier texte converti en UTF-8 depuis {$validated['encoding']}.",
            'version_id' => $version->id,
            'encoding' => 'UTF-8',
            'text_length' => mb_strlen($utf8Text, 'UTF-8'),
        ], 200);
    }

    public function clearPageMarkers(Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);

        $path = $version->getXMLFilePath();
        if (! is_file($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fichier XML introuvable pour cette version.',
            ], 404);
        }

        $existingContent = file_get_contents($path);
        $originalEncoding = $this->detectEncoding($existingContent);
        $utf8Content = $originalEncoding === 'UTF-8'
            ? $existingContent
            : mb_convert_encoding($existingContent, 'UTF-8', $originalEncoding);

        $removed = preg_match_all('/<pb\b[^>]*\/>/i', $utf8Content, $matches);
        $updatedUtf8 = preg_replace('/<pb\b[^>]*\/>\s*/i', '', $utf8Content) ?? $utf8Content;
        $contentToWrite = $originalEncoding === 'UTF-8'
            ? $updatedUtf8
            : mb_convert_encoding($updatedUtf8, $originalEncoding, 'UTF-8');

        file_put_contents($path, $contentToWrite);

        $sync = $this->pageMarkerService->syncSidecarWithPb($version);
        $pagination = $this->pageMarkerService->getPaginationInfo($version->id);
        Cache::forget("versions:index:work:{$version->work_id}");
        $this->versionReaderService->clearCache($version);

        return response()->json([
            'status' => 'ok',
            'message' => $removed > 0
                ? "{$removed} marqueur(s) de pagination supprimé(s)."
                : 'Aucun marqueur de pagination à supprimer.',
            'removed' => (int) $removed,
            'pagination' => $pagination,
            'sync' => $sync,
        ], 200);
    }

    /** Build pagination sidecar from <pb> tags present in the version TEI. */
    public function createPaginationFromPb(Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);
        $result = $this->pageMarkerService->createSidecarFromPb($version);
        $this->versionReaderService->clearCache($version);

        if ($result['count'] === 0) {
            return response()->json([
                'status' => 'empty',
                'message' => 'Aucune balise <pb> trouvée dans cette version.',
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'count' => $result['count'],
            'relative' => $result['relative'],
            'version_id' => $version->id,
        ], 200);
    }

    /** Merge <pb> markers from the editor into the pagination sidecar. */
    public function mergePaginationFromPb(Version $version): JsonResponse
    {
        $this->assertVersionEditable($version);
        $result = $this->pageMarkerService->mergeSidecarFromPb($version);

        if (($result['count'] ?? 0) === 0) {
            return response()->json([
                'status' => 'empty',
                'message' => 'Aucune balise <pb> trouvée dans cette version.',
            ], 404);
        }

        $this->versionReaderService->clearCache($version);

        $added = (int) ($result['added'] ?? 0);
        $total = (int) ($result['total'] ?? $result['count'] ?? 0);
        $created = (bool) ($result['created'] ?? false);

        if ($created) {
            $message = "Données de pagination créées depuis l’éditeur ({$total} marqueur(s)).";
        } elseif ($added > 0) {
            $message = "Données de pagination mises à jour : +{$added} marqueur(s) (total {$total}).";
        } else {
            $message = 'Aucun nouveau marqueur détecté depuis l’éditeur.';
        }

        return response()->json([
            'status' => 'ok',
            'added' => $added,
            'total' => $total,
            'version_id' => $version->id,
            'message' => $message,
        ], 200);
    }

    /**
     * Toggle the ignored status of a facsimile page.
     */
    public function toggleIgnoredPage(Version $version, Request $request): JsonResponse
    {
        $this->assertVersionEditorAllowed($version);
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
        ]);

        $filename = $validated['filename'];
        $isNowIgnored = $version->toggleIgnoredPage($filename);

        return response()->json([
            'status' => 'ok',
            'filename' => $filename,
            'ignored' => $isNowIgnored,
        ], 200);
    }

    /* ──────────────────────────── HELPERS ──────────────────────────── */

    private function facsimileStatus(Version $version): array
    {
        $paths = $this->facsimilePaths($version);
        $sourceFiles = $paths['source_files'];
        $legacyDir = base_path('../variance/'.$paths['source_prefix']);

        if (empty($sourceFiles) && $version->is_legacy && File::isDirectory($legacyDir)) {
            $sourceFiles = $this->listLegacyFacsimileFiles($legacyDir);
        }

        $sourceCount = collect($sourceFiles)
            ->reject(fn ($file) => $this->isThumbnail($file))
            ->count();

        $publishedCount = File::exists($paths['dest_dir'])
            ? collect(File::files($paths['dest_dir']))
                ->map(fn ($file) => basename($file))
                ->reject(fn ($file) => $this->isThumbnail($file))
                ->count()
            : 0;

        $queueDisk = Storage::disk('local');
        $queuePrefix = $this->queuePrefix($version);
        $queuedCount = $queueDisk->exists($queuePrefix)
            ? collect($queueDisk->files($queuePrefix))
                ->filter(fn ($path) => preg_match('/\.(jpe?g|png|tiff?)$/i', basename($path)))
                ->count()
            : 0;

        if ($version->is_legacy && $sourceCount > 0) {
            $publishedCount = $sourceCount;
            $queuedCount = 0;
        }

        $totalExpected = $sourceCount + $queuedCount;

        return [
            'source_count' => $sourceCount,
            'published_count' => $publishedCount,
            'queue_count' => $queuedCount,
            'total_expected' => $totalExpected,
            'processing' => $queuedCount > 0,
            'can_publish' => $sourceCount > 0,
            'in_sync' => $sourceCount > 0 && $sourceCount === $publishedCount,
            'source_dir' => $paths['source_prefix'],
            'dest_dir' => $paths['dest_dir'],
            'queue_dir' => $queuePrefix,
        ];
    }

    public function facsimilesProgress(Version $version): JsonResponse
    {
        $version->loadMissing('work.author');

        return response()->json($this->facsimileStatus($version));
    }

    private function facsimilePaths(Version $version): array
    {
        $authorFolder = $version->work->author->folder;
        $workFolder = $version->work->folder;
        $versionFolder = $version->folder;

        $sourcePrefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk = Storage::disk('public');

        $files = $disk->exists($sourcePrefix)
            ? collect($disk->files($sourcePrefix))
                ->map(fn ($path) => basename($path))
                ->filter(fn ($name) => preg_match('/\.(jpe?g|png)$/i', $name))
                ->values()
                ->toArray()
            : [];

        $destDir = "/var/www/variance/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";

        return [
            'source_prefix' => $sourcePrefix,
            'source_exists' => $disk->exists($sourcePrefix),
            'source_files' => $files,
            'dest_dir' => $destDir,
        ];
    }

    private function listLegacyFacsimileFiles(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn ($name) => preg_match('/\.(jpe?g|png)$/i', $name))
            ->values()
            ->toArray();
    }

    private function purgeFacsimileStorage(Version $version, bool $includePublished = true): bool
    {
        $version->loadMissing('work.author');

        return $this->purgeFacsimileStorageByFolders(
            $version->work->author->folder ?? '',
            $version->work->folder ?? '',
            $version->folder ?? '',
            $includePublished
        );
    }

    private function purgeFacsimileStorageByFolders(
        string $authorFolder,
        string $workFolder,
        string $versionFolder,
        bool $includePublished = true
    ): bool {
        if ($authorFolder === '' || $workFolder === '' || $versionFolder === '') {
            return false;
        }

        $removed = false;
        $sourcePrefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $destDir = "/var/www/variance/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk = Storage::disk('public');

        if ($disk->deleteDirectory($sourcePrefix)) {
            $removed = true;
        }

        $legacySourceDir = base_path('../variance/'.$sourcePrefix);
        if (is_dir($legacySourceDir)) {
            File::deleteDirectory($legacySourceDir);
            $removed = true;
        }

        if ($includePublished && File::isDirectory($destDir)) {
            File::deleteDirectory($destDir);
            $removed = true;
        }

        $queuePrefix = $this->queuePrefixFromFolders($authorFolder, $workFolder, $versionFolder);
        $queueDisk = Storage::disk('local');
        if ($queueDisk->deleteDirectory($queuePrefix)) {
            $removed = true;
        } else {
            $queueAbsolute = storage_path('app/'.trim($queuePrefix, '/'));
            if (is_dir($queueAbsolute)) {
                File::deleteDirectory($queueAbsolute);
                $removed = true;
            }
        }

        $this->filesystemCleanupService->pruneEmptyDirectories([
            storage_path("app/public/uploads/{$authorFolder}/{$workFolder}"),
            storage_path("app/public/uploads/{$authorFolder}"),
            base_path("../variance/uploads/{$authorFolder}/{$workFolder}"),
            base_path("../variance/uploads/{$authorFolder}"),
            storage_path("app/private/facsimile_queue/{$authorFolder}/{$workFolder}"),
            storage_path("app/private/facsimile_queue/{$authorFolder}"),
        ]);

        return $removed;
    }

    private function facsimileCancelMarkerPath(int $versionId): string
    {
        return storage_path('app/private/facsimile_cancel/'.$versionId.'.flag');
    }

    private function setFacsimileCancelMarker(int $versionId): void
    {
        $path = $this->facsimileCancelMarkerPath($versionId);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, (string) now()->timestamp);
    }

    private function queuePrefix(Version $version): string
    {
        return $this->queuePrefixFromFolders(
            $version->work->author->folder ?? '',
            $version->work->folder ?? '',
            $version->folder ?? ''
        );
    }

    private function queuePrefixFromFolders(string $authorFolder, string $workFolder, string $versionFolder): string
    {
        return trim(sprintf('facsimile_queue/%s/%s/%s', $authorFolder, $workFolder, $versionFolder), '/');
    }

    private function deleteVersionPrivateArtifacts(int $versionId): void
    {
        $backupRoot = storage_path('app/private/facsimile_backups/'.$versionId);
        if (is_dir($backupRoot)) {
            File::deleteDirectory($backupRoot);
        }

        $cancelMarker = $this->facsimileCancelMarkerPath($versionId);
        if (is_file($cancelMarker)) {
            @unlink($cancelMarker);
        }

        $this->filesystemCleanupService->pruneEmptyDirectories([
            storage_path('app/private/facsimile_backups'),
            storage_path('app/private/facsimile_cancel'),
        ]);
    }

    private function formatManifestComparison(Version $version, Comparison $comparison, string $role, array $fallbackNames): array
    {
        $meta = $this->readManifestMetadata($version, $comparison, $role, $fallbackNames);

        $sourceName = $comparison->sourceVersion?->name ?? "Version {$comparison->source_id}";
        $targetName = $comparison->targetVersion?->name ?? "Version {$comparison->target_id}";
        $roleLabel = $role === 'source' ? 'Source' : 'Cible';

        return [
            'comparison_id' => $comparison->id,
            'comparison_label' => sprintf('#%d · %s → %s', $comparison->id, $sourceName, $targetName),
            'comparison_folder' => $comparison->folder,
            'role' => $role,
            'role_label' => $roleLabel,
            'read_only' => (bool) ($version->is_legacy || $version->work?->is_legacy || $comparison->is_legacy),
            'selected' => $meta['selected'],
            'count' => $meta['count'],
            'exists' => $meta['exists'],
            'inferred' => $meta['inferred'],
            'file' => $meta['file'],
            'updated_at' => $meta['updated_at'],
        ];
    }

    private function manifestRelativePath(Version $version, Comparison $comparison, string $role): ?string
    {
        $role = strtolower($role);
        if (! in_array($role, ['source', 'target'], true)) {
            return null;
        }

        $version->loadMissing('work.author', 'work');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        $versionFolder = $version->folder ?? null;

        if (! $authorFolder || ! $workFolder || ! $versionFolder) {
            return null;
        }

        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));
        $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $filename = sprintf('images_%s_%s.json', $role, $baseName);

        return "{$relativeDir}/{$filename}";
    }

    private function readManifestMetadata(Version $version, Comparison $comparison, string $role, array $fallbackNames = []): array
    {
        $relativePath = $this->manifestRelativePath($version, $comparison, $role);
        if (! $relativePath) {
            return [
                'exists' => false,
                'selected' => $fallbackNames,
                'count' => count($fallbackNames),
                'file' => null,
                'updated_at' => null,
                'inferred' => ! empty($fallbackNames),
            ];
        }

        $disk = Storage::disk('public');
        $raw = null;
        $exists = false;
        $updatedAt = null;

        if ($disk->exists($relativePath)) {
            $raw = $disk->get($relativePath);
            $exists = true;
            $updatedAt = $disk->lastModified($relativePath);
        } else {
            $legacyPath = base_path('../variance/'.$relativePath);
            if (is_file($legacyPath)) {
                $raw = File::get($legacyPath);
                $exists = true;
                $updatedAt = filemtime($legacyPath) ?: null;
            }
        }

        $selected = [];
        if ($raw !== null) {
            $entries = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($entries)) {
                foreach ($entries as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }
                    $path = $entry['big'] ?? $entry['small'] ?? null;
                    if ($path) {
                        $name = basename($path);
                        if ($name !== '') {
                            $selected[] = $name;
                        }
                    }
                }
            }
        }

        $inferred = false;
        if (! $exists && ! empty($fallbackNames)) {
            $selected = $fallbackNames;
            $inferred = true;
        }

        return [
            'exists' => $exists,
            'selected' => $selected,
            'count' => count($selected),
            'file' => $exists ? $relativePath : null,
            'updated_at' => $updatedAt,
            'inferred' => $inferred,
        ];
    }

    private function buildManifestEntryFromName(Version $version, string $fileName): ?array
    {
        $version->loadMissing('work.author', 'work');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        $versionFolder = $version->folder ?? null;

        if (! $authorFolder || ! $workFolder || ! $versionFolder) {
            return null;
        }

        $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk = Storage::disk('public');
        $imageRel = "{$relativeDir}/{$fileName}";
        if (! $disk->exists($imageRel)) {
            return null;
        }
        $thumbRel = preg_replace('/(\.\w+)$/', '_thumb$1', $imageRel);
        $smallRel = $disk->exists($thumbRel) ? $thumbRel : $imageRel;

        return [
            'small' => '/'.ltrim($smallRel, '/'),
            'big' => '/'.ltrim($imageRel, '/'),
        ];
    }

    private function mirrorToLegacy(string $relativeDir, string $fileName, string $contents): void
    {
        $legacyDir = base_path('../variance/'.$relativeDir);
        if (! is_dir($legacyDir)) {
            File::makeDirectory($legacyDir, 0775, true, true);
        }
        File::put($legacyDir.DIRECTORY_SEPARATOR.$fileName, $contents);
    }

    private function isThumbnail(string $filename): bool
    {
        return str_contains(strtolower($filename), '_thumb');
    }

    private function detectEncoding(string $content): string
    {
        $detected = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);

        return $detected ?: 'UTF-8';
    }

    private function assertWorkEditable(Work $work): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canEditWork($work)) {
            abort(403, 'Cette œuvre est en lecture seule ou non assignée.');
        }
    }

    private function assertVersionEditable(Version $version): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canEditVersion($version)) {
            abort(403, 'Cette version est en lecture seule ou non assignée.');
        }
    }

    private function assertVersionEditorAllowed(Version $version): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canUseVersionEditor($version)) {
            abort(403, 'Accès limité aux versions assignées.');
        }
    }

    private function assertVersionTextNormalizationAllowed(Version $version): void
    {
        if (! auth()->check()) {
            abort(403, 'Authentification requise.');
        }

        $this->assertVersionEditable($version);
    }

    private function assertComparisonOwnership(Comparison $comparison): void
    {
        $user = auth()->user();
        if (! $user || $user->is_admin) {
            return;
        }

        if ($comparison->is_legacy && request()->isMethod('get')) {
            return;
        }

        if ((int) $comparison->created_by !== (int) $user->id) {
            abort(403, 'Accès limité aux comparaisons personnelles.');
        }
    }
}
