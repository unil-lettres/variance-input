<?php

namespace App\Http\Controllers;

use App\Jobs\ApplyLignesJob;
use App\Models\Work;
use App\Models\Version;
use App\Models\Comparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Services\PageMarkerService;
use App\Services\FilesystemCleanupService;
use App\Support\Txt2TeiInlineMarkup;

class VersionController extends Controller
{
    private const READER_DATASET_SCHEMA_VERSION = 3;

    public function __construct(
        private PageMarkerService $pageMarkerService,
        private FilesystemCleanupService $filesystemCleanupService,
    )
    {
    }

    /* ───────────────────────── PUBLIC ENDPOINTS ───────────────────────── */

    /** List versions for a given work */
    public function index(Request $request)
    {
        $workId = $request->query('work_id');
        if (!$workId) {
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
        if (!empty($versionIds)) {
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
                    'id'         => $version->id,
                    'name'       => $version->name,
                    'folder'     => $version->folder,
                    'work_id'    => $version->work_id,
                    'is_legacy'  => (bool) $version->is_legacy,
                    'is_in_use'  => !empty($comparisonIds),
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
        if (!is_file($path)) {
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
            'work_id'     => 'required|exists:works,id',
            'name'        => 'required|string|max:100',
            'versionFile' => 'required|file|max:8192',
            'strip_indentation'      => 'nullable|boolean',
            'collapse_double_spaces' => 'nullable|boolean',
            'trim_line_ends'         => 'nullable|boolean',
            'trim_file_edges'        => 'nullable|boolean',
            'preserve_nbsp'          => 'nullable|boolean',
            'legacy_typography'      => 'nullable|boolean',
        ]);

        /* 2. Context */
        $work        = Work::findOrFail($validated['work_id']);
        $this->assertWorkEditable($work);
        $shortTitle  = $work->short_title;
        $nextNumber  = Version::where('work_id', $work->id)->count() + 1;
        $baseName    = "{$nextNumber}{$shortTitle}";
        $authorFolder = $work->author->folder ?? null;
        $workFolder   = $work->folder ?? null;
        $folderPath  = 'uploads/versions'; // storage/app/public

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
        $txtFilename    = "{$baseName}.txt";
        $txtStoragePath = "{$folderPath}/{$txtFilename}";
        $request->file('versionFile')->storeAs($folderPath, $txtFilename, 'public');

        /* 4. Read & normalise */
        $fullTxt = storage_path("app/public/{$txtStoragePath}");
        $utf8    = $this->readFileAsUtf8(
            $fullTxt,
            $request->input('original_encoding'),
            false
        );
        if (!$this->isLikelyTextContent($utf8)) {
            throw ValidationException::withMessages([
                'versionFile' => 'Le fichier importé ne semble pas être un texte lisible.',
            ]);
        }
        $teiTitle = $work->title ?: $validated['name'];
        $teiAuthor = $work->author?->name ?: '';
        $tei = $this->buildLegacyTxt2TeiXml($utf8, $teiTitle, $nextNumber, $teiAuthor, $validated['name']);

        /* 7. Save .xml */
        Storage::disk('public')->put("{$folderPath}/{$baseName}.xml", $tei);

        /* 8. DB row */
        $version = Version::create([
            'work_id' => $work->id,
            'name'    => $validated['name'],
            'folder'  => $baseName,
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
            'pagination_done'    => $done,
            'pagination_done_at' => $done ? now() : null,
            'pagination_done_by' => $done ? (auth()->id() ?: null) : null,
        ]);

        $version->loadMissing('paginationDoneBy');
        Cache::forget("versions:index:work:{$version->work_id}");

        return response()->json([
            'status'                  => 'ok',
            'version_id'              => $version->id,
            'pagination_done'         => $version->pagination_done,
            'pagination_done_at'      => $version->pagination_done_at?->getTimestamp(),
            'pagination_done_by'      => $version->pagination_done_by,
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

        $disk  = Storage::disk('public');
        $base  = $version->folder;
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
        $lignesRemoved     = false;
        $paginationRemoved = false;
        $lignesPath        = null;
        $paginationPath    = null;
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
                'path'       => $lignesPath ?? null,
                'pagination_path' => $paginationPath ?? null,
                'error'      => $e->getMessage(),
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
            $message .= ' — fichiers introuvables : ' . implode(', ', $missing);
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
        $removed    = $this->purgeFacsimileStorage($version, includePublished: true);
        Cache::forget("versions:facsimiles:{$version->id}");
        Cache::forget("versions:index:work:{$version->work_id}");
        $facsimiles = $this->facsimileStatus($version);

        return response()->json([
            'status'     => $removed ? 'reset' : 'noop',
            'message'    => $removed
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
            'status'   => 'reset',
            'message'  => 'Progression réinitialisée.',
            'lignes'   => $info,
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
                'status'   => 'busy',
                'message'  => 'Impossible de supprimer le fichier _lignes pendant un traitement en cours.',
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
            'status'     => 'ok',
            'message'    => $message,
            'lignes'     => $info,
            'pagination' => $pagination,
            'progress'   => $updatedProgress,
            'removed'    => $removed,
        ]);
    }

    public function manifestComparisons(Version $version): JsonResponse
    {
        $version->loadMissing('work.author', 'work');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder   = $version->work->folder ?? null;
        if (!$authorFolder || !$workFolder) {
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
            'role'   => 'required|in:source,target',
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
        $workFolder   = $version->work->folder ?? null;
        if (!$authorFolder || !$workFolder || !$version->folder) {
            return response()->json([
                'message' => 'Impossible de déterminer l\'emplacement des fac-similés.',
            ], 422);
        }

        $relativePath = $this->manifestRelativePath($version, $comparison, $role);
        if (!$relativePath) {
            return response()->json([
                'message' => 'Chemin de manifeste invalide.',
            ], 422);
        }

        $versionEntries = $version->collectManifestEntries();
        $entriesByName = [];
        foreach ($versionEntries as $entry) {
            $path = $entry['big'] ?? null;
            if (!$path) {
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
            'status'     => 'ok',
            'selected'   => $metadata['selected'],
            'count'      => $metadata['count'],
            'exists'     => $metadata['exists'],
            'file'       => $metadata['file'],
            'updated_at' => $metadata['updated_at'],
            'inferred'   => $metadata['inferred'],
        ]);
    }

    /** Serve TEI */
    public function viewXmlClean($id)
    {
        $version = DB::table('versions')->find($id) ?? abort(404);
        $path    = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        file_exists($path) || abort(404);
        return response(file_get_contents($path), 200)->header('Content-Type', 'application/xml');
    }

    /** Download version text */
    public function downloadText(Version $version)
    {
        $path = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        if (!is_file($path)) {
            abort(404);
        }

        return response()->download($path, "{$version->folder}.txt");
    }

    public function downloadXml(Version $version)
    {
        $path = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        if (!is_file($path)) {
            abort(404);
        }

        return response()->download($path, "{$version->folder}.xml");
    }

    public function publishFacsimiles(Request $request)
    {
        $validated = $request->validate([
            'version_id' => 'required|exists:versions,id',
        ]);

        $version = Version::with('work.author')->findOrFail($validated['version_id']);
        $this->assertVersionEditable($version);
        $paths   = $this->facsimilePaths($version);

        if (!$paths['source_exists'] || empty($paths['source_files'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucun fac-similé à publier pour cette version.',
            ], 400);
        }

        File::ensureDirectoryExists($paths['dest_dir']);
        File::cleanDirectory($paths['dest_dir']);

        $copied = 0;
        $disk   = Storage::disk('public');

        foreach ($paths['source_files'] as $fileName) {
            $contents = $disk->get($paths['source_prefix'] . '/' . $fileName);
            File::put($paths['dest_dir'] . '/' . $fileName, $contents);
            $copied++;
        }

        $manifestInfo = $this->publishManifestsForVersion($version);
        $status       = $this->facsimileStatus($version);

        return response()->json([
            'status'     => 'ok',
            'message'    => "{$copied} fichier(s) publié(s) dans {$paths['dest_dir']}",
            'facsimiles' => $status,
            'manifests'  => $manifestInfo,
        ]);
    }

    public function applyPageMarkers(Request $request, Version $version)
    {
        $this->assertVersionEditable($version);
        $validated = $request->validate([
            'lignes'         => 'required|file|max:4096',
            'clear_existing' => 'sometimes|boolean',
            'replace_existing' => 'sometimes|boolean',
        ]);

        $tempPath = $request->file('lignes')->store('tmp/lignes', 'local');

        $progressFile = storage_path('app/tmp/pager/' . $version->id . '.json');
        if (is_file($progressFile)) {
            $payload = json_decode(@file_get_contents($progressFile), true);
            $status = $payload['status'] ?? null;
            if (in_array($status, ['queued', 'running'], true)) {
                Storage::disk('local')->delete($tempPath);
                return response()->json([
                    'status'  => 'busy',
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
            'status'      => 'queued',
            'version_id'  => $version->id,
        ], 202);
    }

    public function uploadLignes(Request $request, Version $version)
    {
        $this->assertVersionEditable($version);
        $request->validate([
            'lignes' => 'required|file|max:4096',
        ]);

        $file     = $request->file('lignes');
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
                'status'  => 'error',
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
            'status'     => 'queued',
            'message'    => 'Import du fichier _lignes en cours…',
            'lignes'     => $info,
            'pagination' => $pagination,
            'progress'   => $progress,
        ], 202);
    }

    public function pageMarkersProgress(Version $version)
    {
        $progressFile = storage_path('app/tmp/pager/' . $version->id . '.json');
        if (!file_exists($progressFile)) {
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
        if (!Storage::disk('local')->exists($relative)) {
            abort(404, 'Fichier _lignes introuvable.');
        }

        $filename = $version->folder . '_lignes.txt';
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
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function paginationInfo(Version $version)
    {
        $info = $this->pageMarkerService->getPaginationInfo($version->id);
        if (!$info) {
            return response()->json([
                'status'     => 'missing',
                'version_id' => $version->id,
            ], 404);
        }

        return response()->json($info + ['version_id' => $version->id], 200);
    }

    public function readerData(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->normalizeSourceEncodingHint($request->query('encoding'));
        $requestedTextSource = $this->normalizeReaderTextSourceHint($request->query('text_source'));
        $dataset = $this->readerDataset($version, $requestedEncoding, $requestedTextSource);
        return response()->json($this->buildReaderResponsePayload($version, $dataset), 200);
    }

    public function rebuildReaderData(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->normalizeSourceEncodingHint($request->input('encoding', $request->query('encoding')));
        $requestedTextSource = $this->normalizeReaderTextSourceHint($request->input('text_source', $request->query('text_source')));
        $this->clearReaderDatasetCache($version->id);
        $dataset = $this->readerDataset($version, $requestedEncoding, $requestedTextSource);

        return response()->json([
            'status' => 'ok',
            'message' => 'Lecteur reconstruit pour cette version.',
        ] + $this->buildReaderResponsePayload($version, $dataset), 200);
    }

    private function buildReaderResponsePayload(Version $version, array $dataset): array
    {
        $pagePlans = is_array($dataset['page_plans'] ?? null)
            ? $dataset['page_plans']
            : $this->readerPagePlans($dataset['text'] ?? null, $dataset['markers'] ?? [], $dataset['facsimiles'] ?? []);
        $pageSummaries = array_map(function (array $page) {
            return [
                'label' => $page['label'] ?? null,
                'image' => $page['image'] ?? null,
                'line' => $page['line'] ?? null,
                'imageCode' => $page['imageCode'] ?? null,
                'anchorOffset' => $page['anchorOffset'] ?? null,
                'anchorPhrase' => $page['anchorPhrase'] ?? null,
                'guessed' => $page['guessed'] ?? false,
            ];
        }, $pagePlans);
        $currentPage = isset($pagePlans[0]) ? $this->materializeReaderPage($pagePlans[0], $dataset['text'] ?? null) : null;

        return [
            'version_id' => $version->id,
            'version_name' => $version->name,
            'version_folder' => $version->folder,
            'text_available' => $dataset['text_available'],
            'text_length' => $dataset['text_length'],
            'text_encoding' => $dataset['text_encoding'],
            'text_source' => $dataset['text_source'],
            'text_source_label' => $dataset['text_source_label'],
            'text_source_options' => $dataset['text_source_options'] ?? [],
            'facsimiles' => $dataset['facsimiles'],
            'pages' => $pageSummaries,
            'page_count' => count($pageSummaries),
            'current_page_index' => $currentPage ? 0 : null,
            'current_page' => $currentPage,
            'pagination' => $dataset['pagination'],
        ];
    }

    public function readerProgress(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->normalizeSourceEncodingHint($request->query('encoding'));
        $requestedTextSource = $this->normalizeReaderTextSourceHint($request->query('text_source'));
        $snapshot = Cache::get($this->readerProgressCacheKey($version->id, $requestedEncoding, $requestedTextSource));

        if (!is_array($snapshot)) {
            return response()->json([
                'version_id' => $version->id,
                'status' => 'idle',
                'percent' => 0,
                'label' => 'En attente du chargement du viewer.',
                'updated_at' => null,
            ], 200);
        }

        return response()->json([
            'version_id' => $version->id,
            'status' => $snapshot['status'] ?? 'running',
            'percent' => max(0, min(100, (int) ($snapshot['percent'] ?? 0))),
            'label' => $snapshot['label'] ?? 'Chargement du viewer…',
            'updated_at' => $snapshot['updated_at'] ?? null,
        ], 200);
    }

    public function readerPage(Request $request, Version $version): JsonResponse
    {
        $requestedEncoding = $this->normalizeSourceEncodingHint($request->query('encoding'));
        $requestedTextSource = $this->normalizeReaderTextSourceHint($request->query('text_source'));
        $index = max(0, (int) $request->query('index', 0));
        $dataset = $this->readerDataset($version, $requestedEncoding, $requestedTextSource);
        $pagePlans = is_array($dataset['page_plans'] ?? null)
            ? $dataset['page_plans']
            : $this->readerPagePlans($dataset['text'] ?? null, $dataset['markers'] ?? [], $dataset['facsimiles'] ?? []);
        $pagePlan = $pagePlans[$index] ?? null;

        if (!$pagePlan) {
            return response()->json([
                'status' => 'missing',
                'message' => 'Page du lecteur introuvable.',
                'index' => $index,
                'page_count' => count($pagePlans),
            ], 404);
        }

        return response()->json([
            'version_id' => $version->id,
            'page_index' => $index,
            'page_count' => count($pagePlans),
            'text_encoding' => $dataset['text_encoding'],
            'text_source' => $dataset['text_source'],
            'page' => $this->materializeReaderPage($pagePlan, $dataset['text'] ?? null),
        ], 200);
    }

    public function convertTextToUtf8(Request $request, Version $version): JsonResponse
    {
        $this->assertVersionTextNormalizationAllowed($version);

        $validated = $request->validate([
            'encoding' => 'required|string|in:UTF-8,Windows-1252,ISO-8859-1,Mac Roman',
        ]);

        $textPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        if (!is_file($textPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fichier texte introuvable pour cette version.',
            ], 404);
        }

        $normalizedHint = $this->normalizeSourceEncodingHint($validated['encoding']);
        $utf8Text = $this->readFileAsUtf8($textPath, $normalizedHint);
        File::put($textPath, $utf8Text);

        clearstatcache(true, $textPath);
        $this->clearReaderDatasetCache($version->id);

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
        if (!is_file($path)) {
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
        $this->clearReaderDatasetCache($version->id);

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
        $this->clearReaderDatasetCache($version->id);

        if ($result['count'] === 0) {
            return response()->json([
                'status'  => 'empty',
                'message' => 'Aucune balise <pb> trouvée dans cette version.',
            ], 404);
        }

        return response()->json([
            'status'     => 'ok',
            'count'      => $result['count'],
            'relative'   => $result['relative'],
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
                'status'  => 'empty',
                'message' => 'Aucune balise <pb> trouvée dans cette version.',
            ], 404);
        }

        $this->clearReaderDatasetCache($version->id);

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
            'status'     => 'ok',
            'added'      => $added,
            'total'      => $total,
            'version_id' => $version->id,
            'message'    => $message,
        ], 200);
    }

    public function warmReaderCache(Version $version, ?string $requestedEncoding = null, ?string $requestedTextSource = null): array
    {
        return $this->readerDataset($version, $requestedEncoding, $requestedTextSource);
    }

    public function clearReaderCache(Version $version): void
    {
        $this->clearReaderDatasetCache($version->id);
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
            'status'   => 'ok',
            'filename' => $filename,
            'ignored'  => $isNowIgnored,
        ], 200);
    }

    /* ──────────────────────────── HELPERS ──────────────────────────── */

    /** Detect + convert arbitrary bytes to UTF‑8 LF */
    private function readFileAsUtf8(string $absPath, ?string $hint = null, bool $normalizeLineEndings = true): string
    {
        $bytes = file_get_contents($absPath);
        if ($bytes === false) {
            throw new \RuntimeException("Impossible de lire le fichier source : {$absPath}");
        }

        $hintEncoding = $this->normalizeSourceEncodingHint($hint);
        $enc = $hintEncoding;
        if ($enc === null) {
            $enc = mb_detect_encoding(
                $bytes,
                ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'],
                true
            ) ?: null;
        }
        $enc ??= 'Windows-1252';

        $utf8 = $this->convertToUtf8($bytes, $enc);

        // For unknown/no-BOM legacy files, prefer Mac Roman when decoded text quality is better.
        if ($hintEncoding === null) {
            $utf8 = $this->preferMacRomanIfCleaner($bytes, $enc, $utf8);
        }

        // Last-chance fallback for files reported as unknown/no BOM:
        // many old Mac exports are Mac Roman.
        if (!mb_check_encoding($utf8, 'UTF-8')) {
            $utf8 = $this->convertToUtf8($bytes, 'Macintosh');
        }

        return $normalizeLineEndings
            ? str_replace(["\r\n", "\r"], "\n", $utf8)
            : $utf8;
    }

    private function normalizeSourceEncodingHint(?string $hint): ?string
    {
        if (!$hint) {
            return null;
        }

        $h = strtoupper(trim($hint));
        if ($h === '') {
            return null;
        }

        if (str_contains($h, 'UTF-8')) {
            return 'UTF-8';
        }
        if (str_contains($h, 'MAC') && str_contains($h, 'ROMAN')) {
            return 'Macintosh';
        }
        if (str_contains($h, 'MACINTOSH')) {
            return 'Macintosh';
        }
        if (str_contains($h, 'WINDOWS-1252') || str_contains($h, 'CP1252')) {
            return 'Windows-1252';
        }
        if (str_contains($h, 'ISO-8859')) {
            return 'ISO-8859-1';
        }
        if (str_contains($h, 'ASCII')) {
            return 'ASCII';
        }

        return null;
    }

    private function convertToUtf8(string $bytes, string $sourceEncoding): string
    {
        $source = trim($sourceEncoding) !== '' ? $sourceEncoding : 'Windows-1252';

        if (strcasecmp($source, 'Macintosh') === 0 && function_exists('iconv')) {
            $converted = @iconv('MACINTOSH', 'UTF-8//IGNORE', $bytes);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return mb_convert_encoding($bytes, 'UTF-8', $source);
    }

    private function preferMacRomanIfCleaner(string $bytes, string $detectedEncoding, string $decoded): string
    {
        $normalized = strtoupper(trim($detectedEncoding));
        if (!in_array($normalized, ['WINDOWS-1252', 'ISO-8859-1', 'ASCII'], true)) {
            return $decoded;
        }

        $macDecoded = $this->convertToUtf8($bytes, 'Macintosh');
        if ($macDecoded === '' || !mb_check_encoding($macDecoded, 'UTF-8')) {
            return $decoded;
        }

        $decodedScore = $this->decodedTextNoiseScore($decoded);
        $macScore = $this->decodedTextNoiseScore($macDecoded);

        // Pick Mac Roman when it clearly removes control/mojibake noise.
        return ($macScore + 3) < $decodedScore ? $macDecoded : $decoded;
    }

    private function decodedTextNoiseScore(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        $score = 0;
        $score += preg_match_all('/[\x{0080}-\x{009F}]/u', $content) * 10; // C1 controls
        $score += preg_match_all('/[\x{FFFD}]/u', $content) * 6; // replacement chars
        $score += preg_match_all('/[\x{00D5}\x{0152}\x{0153}\x{02C6}\x{0160}\x{2039}\x{203A}\x{0178}\x{017E}\x{2122}]/u', $content) * 2;

        return $score;
    }

    /**
     * Heuristic guard to reject obvious binary uploads while accepting legacy encodings.
     */
    private function isLikelyTextContent(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        if (strpos($content, "\0") !== false) {
            return false;
        }

        $sample = mb_substr($content, 0, 12000, 'UTF-8');
        $len = mb_strlen($sample, 'UTF-8');
        if ($len === 0) {
            return false;
        }

        $controls = 0;
        $chars = preg_split('//u', $sample, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $ord = mb_ord($ch, 'UTF-8');
            if ($ord === false) {
                continue;
            }
            // Allow tab/newline/carriage return; count other C0 controls.
            if ($ord < 32 && !in_array($ord, [9, 10, 13], true)) {
                $controls++;
            }
        }

        // If >2% control chars, likely binary.
        return ($controls / max(1, $len)) < 0.02;
    }

    private function buildLegacyTxt2TeiXml(
        string $txt,
        string $title,
        int $versionNumber,
        string $author = '',
        string $versionName = ''
    ): string
    {
        $txt = $this->normalizeTxt2TeiCharacters($txt);
        $txt = $this->collapseTxt2TeiSpacesAndTabs($txt);

        $escapedText = Txt2TeiInlineMarkup::escapeWithItalicMarkup($txt);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<TEI xml:id=\"v{$versionNumber}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n"
            . $this->buildTeiHeaderXml($title, $author, $versionName)
            . "  <text>\n"
            . "    <body>\n"
            . "      <p>{$escapedText}</p>\n"
            . "    </body>\n"
            . "  </text>\n"
            . "</TEI>\n";
    }

    private function buildTeiHeaderXml(
        string $title,
        string $author = '',
        string $versionName = '',
        string $editor = '',
        string $publisher = '',
        string $publicationDate = '',
        string $sourceDate = ''
    ): string {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $lines = [
            "  <teiHeader>",
            "    <fileDesc>",
            "      <titleStmt>",
        ];

        if ($title !== '') {
            $lines[] = "        <title>{$escape($title)}</title>";
        }
        if ($versionName !== '') {
            $lines[] = "        <title type=\"version\">{$escape($versionName)}</title>";
        }
        if ($author !== '') {
            $lines[] = "        <author>{$escape($author)}</author>";
        }
        if ($editor !== '') {
            $lines[] = "        <editor>{$escape($editor)}</editor>";
        }
        $lines[] = "      </titleStmt>";

        if ($publisher !== '' || $publicationDate !== '') {
            $lines[] = "      <publicationStmt>";
            if ($publisher !== '') {
                $lines[] = "        <publisher>{$escape($publisher)}</publisher>";
            }
            if ($publicationDate !== '') {
                $lines[] = "        <date>{$escape($publicationDate)}</date>";
            }
            $lines[] = "      </publicationStmt>";
        }

        $lines[] = "      <sourceDesc>";
        $lines[] = "        <bibl>";
        if ($author !== '') {
            $lines[] = "          <author>{$escape($author)}</author>";
        }
        if ($title !== '') {
            $lines[] = "          <title>{$escape($title)}</title>";
        }
        if ($versionName !== '') {
            $lines[] = "          <title type=\"version\">{$escape($versionName)}</title>";
        }
        if ($publisher !== '') {
            $lines[] = "          <publisher>{$escape($publisher)}</publisher>";
        }
        if ($sourceDate !== '') {
            $lines[] = "          <date>{$escape($sourceDate)}</date>";
        }
        $lines[] = "        </bibl>";
        $lines[] = "      </sourceDesc>";
        $lines[] = "    </fileDesc>";
        $lines[] = "  </teiHeader>";

        return implode("\n", $lines) . "\n";
    }

    private function normalizeTxt2TeiCharacters(string $txt): string
    {
        return str_replace(
            [
                "\u{2013}",
                "\u{2212}",
                "\u{2010}",
                "\u{2011}",
                "\u{00AD}",
                "\u{2026}",
                "\u{201C}",
                "\u{201D}",
                "\u{201E}",
                "\u{201F}",
                "\u{2018}",
                "\u{2019}",
                "\u{02BC}",
                "\u{00B4}",
                "\u{02C8}",
                "\u{00A0}",
                "\u{2002}",
                "\u{2003}",
                "\u{2009}",
                "\u{202F}",
                "\u{200B}",
                "\u{FEFF}",
                "\r\n",
                "\r",
            ],
            [
                "\u{2014}",
                "-",
                "-",
                "-",
                "",
                "...",
                '"',
                '"',
                '"',
                '"',
                "'",
                "'",
                "'",
                "'",
                "'",
                " ",
                " ",
                " ",
                " ",
                " ",
                "",
                "",
                "\n",
                "\n",
            ],
            $txt
        );
    }

    private function collapseTxt2TeiSpacesAndTabs(string $txt): string
    {
        $txt = str_replace("\t", ' ', $txt);

        return preg_replace('/ {2,}/', ' ', $txt) ?? $txt;
    }

    private function facsimileStatus(Version $version): array
    {
        $paths = $this->facsimilePaths($version);
        $sourceFiles = $paths['source_files'];
        $legacyDir = base_path('../variance/' . $paths['source_prefix']);

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

        $queueDisk   = Storage::disk('local');
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
            'source_count'    => $sourceCount,
            'published_count' => $publishedCount,
            'queue_count'     => $queuedCount,
            'total_expected'  => $totalExpected,
            'processing'      => $queuedCount > 0,
            'can_publish'     => $sourceCount > 0,
            'in_sync'         => $sourceCount > 0 && $sourceCount === $publishedCount,
            'source_dir'      => $paths['source_prefix'],
            'dest_dir'        => $paths['dest_dir'],
            'queue_dir'       => $queuePrefix,
        ];
    }

    private function readerFacsimiles(Version $version): array
    {
        return $this->collectReaderFacsimiles($version, false);
    }

    private function readerFacsimilesDetailed(Version $version): array
    {
        return $this->collectReaderFacsimiles($version, true);
    }

    private function collectReaderFacsimiles(Version $version, bool $includeDimensions): array
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (!$authorFolder || !$workFolder) {
            return [];
        }

        $cacheKey = null;
        if ($includeDimensions) {
            $cacheKey = $this->readerFacsimilesCacheKey($version);
            if ($cacheKey) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        $dirRel = "uploads/{$authorFolder}/{$workFolder}/{$version->folder}";
        $disk = Storage::disk('public');
        $legacyDir = base_path('../variance/' . $dirRel);
        $useLegacy = false;

        if ($disk->exists($dirRel)) {
            $all = collect($disk->files($dirRel))
                ->map(fn ($path) => [
                    'name' => basename($path),
                    'path' => $path,
                    'absolute' => $disk->path($path),
                ]);
        } elseif (File::isDirectory($legacyDir)) {
            $useLegacy = true;
            $all = collect(File::files($legacyDir))
                ->map(fn ($file) => [
                    'name' => $file->getFilename(),
                    'path' => $file->getFilename(),
                    'absolute' => $file->getPathname(),
                ]);
        } else {
            return [];
        }

        $facsimiles = $all
            ->filter(fn ($entry) => preg_match('/\.(jpe?g|png)$/i', $entry['name']) && !str_contains($entry['name'], '_thumb'))
            ->map(function (array $entry) use ($disk, $dirRel, $legacyDir, $useLegacy, $includeDimensions) {
                $thumbName = preg_replace('/(\.\w+)$/', '_thumb$1', $entry['name']);
                $thumbPath = $useLegacy ? $legacyDir . '/' . $thumbName : $dirRel . '/' . $thumbName;
                $thumbExists = $useLegacy ? is_file($thumbPath) : $disk->exists($thumbPath);

                $width = null;
                $height = null;
                $sizeBytes = null;
                $sizeHuman = null;
                if ($includeDimensions && is_file($entry['absolute'])) {
                    $sizeBytes = filesize($entry['absolute']) ?: 0;
                    $sizeHuman = $this->humanReadableSize((int) $sizeBytes);
                    $info = @getimagesize($entry['absolute']);
                    if (is_array($info)) {
                        $width = $info[0] ?? null;
                        $height = $info[1] ?? null;
                    }
                }

                $bigUrl = $useLegacy
                    ? legacy_url($dirRel . '/' . $entry['name'])
                    : admin_url('storage/' . ltrim($entry['path'], '/'));
                $thumbUrl = null;
                if ($thumbExists) {
                    $thumbUrl = $useLegacy
                        ? legacy_url($dirRel . '/' . $thumbName)
                        : admin_url('storage/' . ltrim($thumbPath, '/'));
                }

                return [
                    'name' => $entry['name'],
                    'image_code' => $this->readerImageCode($entry['name']),
                    'big' => $bigUrl,
                    'thumb' => $thumbUrl,
                    'hasThumb' => $thumbExists,
                    'size_bytes' => $sizeBytes,
                    'size_human' => $sizeHuman,
                    'width' => $width,
                    'height' => $height,
                ];
            })
            ->sortBy(fn (array $entry) => $entry['image_code'] ?: $entry['name'], SORT_NATURAL)
            ->values()
            ->all();

        if ($includeDimensions && $cacheKey) {
            Cache::put($cacheKey, $facsimiles, now()->addMinutes(30));
        }

        return $facsimiles;
    }

    private function readerFacsimilesCacheKey(Version $version): ?string
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (!$authorFolder || !$workFolder) {
            return null;
        }

        $dirRel = "uploads/{$authorFolder}/{$workFolder}/{$version->folder}";
        $disk = Storage::disk('public');
        $legacyDir = base_path('../variance/' . $dirRel);

        if ($disk->exists($dirRel)) {
            $files = $disk->files($dirRel);
            $fingerprint = array_map(function (string $path) use ($disk): array {
                return [
                    'name' => basename($path),
                    'mtime' => (int) @filemtime($disk->path($path)),
                    'size' => (int) $disk->size($path),
                ];
            }, $files);
        } elseif (File::isDirectory($legacyDir)) {
            $files = File::files($legacyDir);
            $fingerprint = array_map(function (\SplFileInfo $file): array {
                return [
                    'name' => $file->getFilename(),
                    'mtime' => (int) $file->getMTime(),
                    'size' => (int) $file->getSize(),
                ];
            }, $files);
        } else {
            return null;
        }

        return 'versions:reader-facsimiles:' . $version->id . ':' . md5(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    private function readerDataset(Version $version, ?string $requestedEncoding, ?string $requestedTextSource): array
    {
        $version->loadMissing('work.author');
        $fingerprint = $this->readerDatasetFingerprint($version);
        $nonce = (int) Cache::get($this->readerDatasetNonceKey($version->id), 0);
        $cacheKey = $this->readerDatasetCacheKey($version->id, $requestedEncoding, $requestedTextSource, $fingerprint, $nonce);
        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 6, 'Vérification du cache du lecteur…');

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Lecteur prêt.', 'ready');
            return $cached;
        }

        try {
            $artifact = $this->loadReaderDatasetArtifact($version, $requestedEncoding, $requestedTextSource, $fingerprint);
            if (is_array($artifact)) {
                Cache::put($cacheKey, $artifact, now()->addMinutes(10));
                $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Lecteur prêt.', 'ready');
                return $artifact;
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $bundle = $this->buildReaderDatasetBundle($version, $requestedEncoding, $requestedTextSource);
            $dataset = $bundle['selected'];
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 84, 'Préparation des pages du lecteur…');
            $dataset['page_plans'] = $this->readerPagePlans($dataset['text'] ?? null, $dataset['markers'] ?? [], $dataset['facsimiles'] ?? []);
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 93, 'Mise en cache du lecteur…');
            $this->storeReaderDatasetArtifact($version, $requestedEncoding, $requestedTextSource, $fingerprint, $dataset);
            $this->warmReaderDatasetArtifacts(
                $version,
                $requestedEncoding,
                $fingerprint,
                $nonce,
                $bundle['variants'] ?? []
            );
            Cache::put($cacheKey, $dataset, now()->addMinutes(10));
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Lecteur prêt.', 'ready');

            return $dataset;
        } catch (\Throwable $e) {
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Échec du chargement du lecteur.', 'error');
            throw $e;
        }
    }

    private function buildReaderDataset(Version $version, ?string $requestedEncoding, ?string $requestedTextSource): array
    {
        return $this->buildReaderDatasetBundle($version, $requestedEncoding, $requestedTextSource)['selected'];
    }

    private function buildReaderDatasetBundle(Version $version, ?string $requestedEncoding, ?string $requestedTextSource): array
    {
        $version->loadMissing('work.author');

        $textPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        $textVariants = [];
        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 18, 'Lecture du texte de version…');
        if (is_file($textPath)) {
            try {
                $versionText = $this->readFileAsUtf8($textPath, $requestedEncoding);
                if ($versionText !== '') {
                    $textVariants['version-txt'] = [
                        'value' => 'version-txt',
                        'text' => $versionText,
                        'label' => 'TXT de version',
                        'origin' => null,
                        'markers' => [],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Could not load version text for reader.', [
                    'version_id' => $version->id,
                    'folder' => $version->folder,
                    'encoding' => $requestedEncoding,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 34, 'Reconstruction éventuelle depuis le XHTML…');
        $fallback = $this->readerTextFromComparisonXhtml($version);
        if (is_array($fallback) && is_string($fallback['text'] ?? null) && $fallback['text'] !== '') {
            $textVariants['comparison-xhtml'] = [
                'value' => 'comparison-xhtml',
                'text' => $fallback['text'],
                'label' => (string) ($fallback['label'] ?? 'XHTML de comparaison'),
                'origin' => (string) ($fallback['origin'] ?? 'pb-xhtml'),
                'markers' => is_array($fallback['markers'] ?? null) ? $fallback['markers'] : [],
            ];
        }

        $selectedTextSource = $requestedTextSource;
        if (!$selectedTextSource || !array_key_exists($selectedTextSource, $textVariants)) {
            $selectedTextSource = array_key_exists('version-txt', $textVariants)
                ? 'version-txt'
                : (array_key_first($textVariants) ?: null);
        }

        $textSourceOptions = array_values(array_map(function (array $variant): array {
            return [
                'value' => (string) ($variant['value'] ?? ''),
                'label' => (string) ($variant['label'] ?? ''),
            ];
        }, $textVariants));

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 52, 'Chargement des repères de pagination…');
        $sidecar = $this->pageMarkerService->getPaginationSidecar($version->id);
        $sidecarMarkers = collect(is_array($sidecar['markers'] ?? null) ? $sidecar['markers'] : [])
            ->filter(fn ($marker) => is_array($marker))
            ->map(function (array $marker) {
                $imageInferred = (bool) ($marker['image_inferred'] ?? false);
                return [
                    'char_index' => max(0, (int) ($marker['char_index'] ?? 0)),
                    'image_code' => $marker['image_code'] ?? $marker['image'] ?? null,
                    'explicit_image_code' => $imageInferred ? null : ($marker['image_code'] ?? $marker['image'] ?? null),
                    'image_inferred' => $imageInferred,
                    'page' => $marker['page'] ?? null,
                    'line' => isset($marker['line']) && is_numeric($marker['line']) ? (int) $marker['line'] : null,
                    'phrase' => $marker['match'] ?? $marker['phrase'] ?? null,
                ];
            })
            ->sortBy('char_index')
            ->values()
            ->all();

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 68, 'Inventaire des fac-similés…');
        $facsimiles = $this->readerFacsimiles($version);
        $paginationInfo = $this->pageMarkerService->getPaginationInfo($version->id);

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 76, 'Assemblage des sources du lecteur…');
        $variantDatasets = [];
        foreach ($textVariants as $sourceKey => $variant) {
            $variantDatasets[$sourceKey] = $this->assembleReaderDatasetPayload(
                is_string($variant['text'] ?? null) ? $variant['text'] : null,
                $requestedEncoding,
                $sourceKey,
                (string) ($variant['label'] ?? 'source texte non précisée'),
                $textSourceOptions,
                is_array($variant['markers'] ?? null) ? $variant['markers'] : [],
                $variant['origin'] ?? null,
                $sidecarMarkers,
                $sidecar['origin'] ?? null,
                $facsimiles,
                $paginationInfo
            );
        }

        if (array_key_exists($selectedTextSource, $variantDatasets)) {
            $selectedDataset = $variantDatasets[$selectedTextSource];
        }

        return [
            'selected' => $selectedDataset ?? $this->assembleReaderDatasetPayload(
                null,
                $requestedEncoding,
                null,
                null,
                $textSourceOptions,
                [],
                null,
                $sidecarMarkers,
                $sidecar['origin'] ?? null,
                $facsimiles,
                $paginationInfo
            ),
            'variants' => $variantDatasets,
        ];
    }

    private function assembleReaderDatasetPayload(
        ?string $text,
        ?string $requestedEncoding,
        ?string $selectedTextSource,
        ?string $textSourceLabel,
        array $textSourceOptions,
        array $variantMarkers,
        ?string $variantOrigin,
        array $sidecarMarkers,
        ?string $sidecarOrigin,
        array $facsimiles,
        ?array $paginationInfo
    ): array {
        $paginationOrigin = null;
        $markers = [];

        if (!empty($sidecarMarkers)) {
            $paginationOrigin = $sidecarOrigin;
            $markers = $sidecarMarkers;
        } elseif ($selectedTextSource === 'comparison-xhtml' && !empty($variantMarkers)) {
            $paginationOrigin = $variantOrigin;
            $markers = $variantMarkers;
        }

        if (is_string($text) && $text !== '' && !empty($markers)) {
            $markers = $this->pageMarkerService->resolveMarkersForPlainText($text, $markers);
        }

        return [
            'text' => is_string($text) ? $text : null,
            'text_available' => is_string($text),
            'text_length' => is_string($text) ? mb_strlen($text, 'UTF-8') : null,
            'text_encoding' => $requestedEncoding ?: 'AUTO',
            'text_source' => is_string($text) ? $selectedTextSource : null,
            'text_source_label' => $textSourceLabel,
            'text_source_options' => $textSourceOptions,
            'facsimiles' => $facsimiles,
            'markers' => $markers,
            'pagination' => [
                'available' => !empty($markers),
                'origin' => $paginationOrigin,
                'marker_count' => count($markers),
                'updated_at' => $paginationInfo['updated_at'] ?? null,
            ],
        ];
    }

    private function warmReaderDatasetArtifacts(
        Version $version,
        ?string $requestedEncoding,
        array $fingerprint,
        int $nonce,
        array $variants
    ): void {
        foreach ($variants as $source => $dataset) {
            if (!is_array($dataset)) {
                continue;
            }

            if (!isset($dataset['page_plans'])) {
                $dataset['page_plans'] = $this->readerPagePlans(
                    $dataset['text'] ?? null,
                    $dataset['markers'] ?? [],
                    $dataset['facsimiles'] ?? []
                );
            }

            $this->storeReaderDatasetArtifact($version, $requestedEncoding, $source, $fingerprint, $dataset);

            Cache::put(
                $this->readerDatasetCacheKey($version->id, $requestedEncoding, $source, $fingerprint, $nonce),
                $dataset,
                now()->addMinutes(10)
            );
        }
    }

    private function readerProgressCacheKey(int $versionId, ?string $requestedEncoding, ?string $requestedTextSource): string
    {
        $encoding = $requestedEncoding ?: 'AUTO';
        $source = $requestedTextSource ?: 'AUTO';
        return 'versions:reader-progress:' . $versionId . ':' . md5($encoding) . ':' . md5($source);
    }

    private function setReaderProgress(
        int $versionId,
        ?string $requestedEncoding,
        ?string $requestedTextSource,
        int $percent,
        string $label,
        string $status = 'running'
    ): void {
        Cache::put(
            $this->readerProgressCacheKey($versionId, $requestedEncoding, $requestedTextSource),
            [
                'status' => $status,
                'percent' => max(0, min(100, $percent)),
                'label' => $label,
                'updated_at' => now()->toIso8601String(),
            ],
            now()->addMinutes(5)
        );
    }

    private function normalizeReaderTextSourceHint(?string $hint): ?string
    {
        $value = strtolower(trim((string) $hint));
        return in_array($value, ['version-txt', 'comparison-xhtml'], true) ? $value : null;
    }

    private function readerDatasetCacheKey(int $versionId, ?string $requestedEncoding, ?string $requestedTextSource, array $fingerprint = [], int $nonce = 0): string
    {
        $encoding = $requestedEncoding ?: 'AUTO';
        $source = $requestedTextSource ?: 'AUTO';
        $fingerprintHash = md5(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');
        return "versions:reader-dataset:{$versionId}:{$nonce}:" . md5($encoding) . ':' . md5($source) . ":{$fingerprintHash}";
    }

    private function readerDatasetNonceKey(int $versionId): string
    {
        return "versions:reader-dataset:nonce:{$versionId}";
    }

    private function clearReaderDatasetCache(int $versionId): void
    {
        $this->removeReaderDatasetArtifacts($versionId);
        Cache::increment($this->readerDatasetNonceKey($versionId));
    }

    private function readerDatasetArtifactRelativePath(int $versionId, ?string $requestedEncoding, ?string $requestedTextSource): string
    {
        $encoding = $requestedEncoding ?: 'AUTO';
        $source = $requestedTextSource ?: 'AUTO';
        return 'reader_cache/' . $versionId . '/' . md5($encoding) . '-' . md5($source) . '.json';
    }

    private function loadReaderDatasetArtifact(Version $version, ?string $requestedEncoding, ?string $requestedTextSource, array $fingerprint): ?array
    {
        $relative = $this->readerDatasetArtifactRelativePath($version->id, $requestedEncoding, $requestedTextSource);
        $disk = Storage::disk('local');
        if (!$disk->exists($relative)) {
            return null;
        }

        try {
            $decoded = json_decode((string) $disk->get($relative), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('Could not decode persisted reader dataset artifact.', [
                'version_id' => $version->id,
                'path' => $relative,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $storedFingerprint = is_array($decoded['fingerprint'] ?? null) ? $decoded['fingerprint'] : null;
        $dataset = is_array($decoded['dataset'] ?? null) ? $decoded['dataset'] : null;
        if (!$storedFingerprint || !$dataset) {
            return null;
        }

        if (($decoded['version_id'] ?? null) !== $version->id || $storedFingerprint !== $fingerprint) {
            return null;
        }

        return $dataset;
    }

    private function storeReaderDatasetArtifact(Version $version, ?string $requestedEncoding, ?string $requestedTextSource, array $fingerprint, array $dataset): void
    {
        $relative = $this->readerDatasetArtifactRelativePath($version->id, $requestedEncoding, $requestedTextSource);
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($relative));
        $payload = [
            'version_id' => $version->id,
            'encoding' => $requestedEncoding ?: 'AUTO',
            'text_source' => $requestedTextSource ?: 'AUTO',
            'generated_at' => time(),
            'fingerprint' => $fingerprint,
            'dataset' => $dataset,
        ];

        $disk->put($relative, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function removeReaderDatasetArtifacts(int $versionId): void
    {
        $dir = 'reader_cache/' . $versionId;
        $disk = Storage::disk('local');
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
    }

    private function readerDatasetFingerprint(Version $version): array
    {
        $textPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        $sidecarRelative = $this->pageMarkerService->paginationRelativePath($version->id);
        $sidecarPath = Storage::disk('local')->exists($sidecarRelative)
            ? Storage::disk('local')->path($sidecarRelative)
            : null;
        $fallbackCandidate = $this->readerFirstComparisonXhtmlCandidate($version);
        $facsimiles = $this->readerFacsimiles($version);

        return [
            'schema_version' => self::READER_DATASET_SCHEMA_VERSION,
            'text' => [
                'path' => is_file($textPath) ? $textPath : null,
                'mtime' => is_file($textPath) ? ((int) @filemtime($textPath)) : null,
                'size' => is_file($textPath) ? ((int) @filesize($textPath)) : null,
            ],
            'sidecar' => [
                'path' => $sidecarPath,
                'mtime' => $sidecarPath && is_file($sidecarPath) ? ((int) @filemtime($sidecarPath)) : null,
                'size' => $sidecarPath && is_file($sidecarPath) ? ((int) @filesize($sidecarPath)) : null,
            ],
            'fallback' => [
                'comparison_id' => $fallbackCandidate['comparison_id'] ?? null,
                'role' => $fallbackCandidate['role'] ?? null,
                'path' => $fallbackCandidate['path'] ?? null,
                'mtime' => isset($fallbackCandidate['path']) && is_file($fallbackCandidate['path']) ? ((int) @filemtime($fallbackCandidate['path'])) : null,
                'size' => isset($fallbackCandidate['path']) && is_file($fallbackCandidate['path']) ? ((int) @filesize($fallbackCandidate['path'])) : null,
            ],
            'facsimiles' => [
                'count' => count($facsimiles),
                'names' => array_values(array_map(
                    static fn (array $entry) => (string) ($entry['name'] ?? ''),
                    $facsimiles
                )),
            ],
        ];
    }

    private function readerTextFromComparisonXhtml(Version $version): ?array
    {
        foreach ($this->readerComparisonXhtmlCandidates($version) as $candidate) {
            $path = $candidate['path'];
            $fileName = $candidate['file_name'];
            $comparisonId = $candidate['comparison_id'];
            $role = $candidate['role'];

            try {
                $contents = File::get($path);
                $text = $this->extractReaderTextFromComparisonXhtml($path);
                if ($text === '') {
                    continue;
                }

                return [
                    'text' => $text,
                    'source' => 'comparison-xhtml',
                    'label' => "Texte reconstruit depuis {$fileName} (#{$comparisonId})",
                    'origin' => 'pb-xhtml',
                    'markers' => $this->pageMarkerService->extractRuntimeMarkersFromComparisonHtml($contents),
                ];
            } catch (\Throwable $e) {
                Log::warning('Could not reconstruct reader text from comparison XHTML.', [
                    'version_id' => $version->id,
                    'comparison_id' => $comparisonId,
                    'role' => $role,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function readerFirstComparisonXhtmlCandidate(Version $version): ?array
    {
        $candidates = $this->readerComparisonXhtmlCandidates($version);
        return $candidates[0] ?? null;
    }

    private function readerComparisonXhtmlCandidates(Version $version): array
    {
        $version->loadMissing('work.author');

        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (!$authorFolder || !$workFolder) {
            return [];
        }

        $comparisons = Comparison::query()
            ->where(function ($query) use ($version) {
                $query->where('source_id', $version->id)
                    ->orWhere('target_id', $version->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'source_id', 'target_id', 'folder']);

        $candidates = [];
        foreach ($comparisons as $comparison) {
            $role = (int) $comparison->source_id === (int) $version->id ? 'source' : 'target';
            $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
            $candidatePaths = [
                storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}/{$fileName}"),
                storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}/{$fileName}"),
                base_path("../variance/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}/{$fileName}"),
                base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}/{$fileName}"),
            ];

            foreach ($candidatePaths as $path) {
                if (!is_file($path)) {
                    continue;
                }

                $candidates[] = [
                    'comparison_id' => (int) $comparison->id,
                    'role' => $role,
                    'file_name' => $fileName,
                    'path' => $path,
                ];
                break;
            }
        }

        return $candidates;
    }

    private function extractReaderTextFromComparisonXhtml(string $path): string
    {
        $contents = File::get($path);
        if ($contents === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
        $normalized = preg_replace('~<span\b[^>]*class="page-marker"[^>]*>.*?</span>~is', '', $normalized) ?? $normalized;
        $normalized = preg_replace('~<pb\b[^>]*/?>~i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('~<br\s*/?>~i', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('~</(p|div|li|section|article|h[1-6]|tr)>~i', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $normalized) ?? $normalized;
        $normalized = strip_tags($normalized);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
        $normalized = preg_replace("/[ \t]+\n/", "\n", $normalized) ?? $normalized;
        $normalized = preg_replace("/\n[ \t]+/", "\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function readerPagePlans(?string $text, array $markers, array $facsimiles): array
    {
        $text = is_string($text) ? $text : '';
        if ($text === '') {
            return [];
        }

        if (empty($markers)) {
            $firstFacsimile = $facsimiles[0] ?? null;
            $firstImageCode = $this->readerImageCode($firstFacsimile['image_code'] ?? $firstFacsimile['name'] ?? null);

            return [[
                'label' => 'Texte complet',
                'image' => $firstFacsimile,
                'start' => 0,
                'end' => mb_strlen($text, 'UTF-8'),
                'line' => null,
                'imageCode' => $firstImageCode,
                'anchorOffset' => null,
                'anchorPhrase' => null,
                'guessed' => false,
            ]];
        }

        $textLength = mb_strlen($text, 'UTF-8');
        $imagesByCode = [];
        foreach ($facsimiles as $facsimile) {
            $code = $this->readerImageCode($facsimile['image_code'] ?? $facsimile['name'] ?? null);
            if ($code && !array_key_exists($code, $imagesByCode)) {
                $imagesByCode[$code] = $facsimile;
            }
        }

        $normalizedMarkers = array_values(array_map(function (array $marker) {
            $marker['resolved_char_index'] = max(0, (int) ($marker['resolved_char_index'] ?? $marker['char_index'] ?? 0));
            $imageInferred = (bool) ($marker['image_inferred'] ?? false);
            $explicitSource = $marker['explicit_image_code']
                ?? ($imageInferred ? null : ($marker['image_code'] ?? $marker['image'] ?? null));
            $explicitImageCode = $this->readerImageCode($explicitSource);
            $marker['explicit_image_code'] = $explicitImageCode;
            $marker['image_code'] = $explicitImageCode ?? $this->readerImageCode($marker['page'] ?? null);
            $marker['page_label'] = trim((string) ($marker['page'] ?? ''));
            return $marker;
        }, $markers));

        usort($normalizedMarkers, static fn (array $a, array $b) => ((int) ($a['resolved_char_index'] ?? 0)) <=> ((int) ($b['resolved_char_index'] ?? 0)));

        $allSequentialLabels = !empty($normalizedMarkers)
            && collect($normalizedMarkers)->every(static fn (array $marker) => preg_match('/^\d+(?:[a-z]+)?$/i', (string) ($marker['page_label'] ?? '')) === 1);
        $exactExplicitMarkerMatches = collect($normalizedMarkers)
            ->filter(static fn (array $marker) => !empty($marker['explicit_image_code']) && array_key_exists((string) $marker['explicit_image_code'], $imagesByCode))
            ->count();
        $useTrailingSequentialAlignment = count($facsimiles) >= count($normalizedMarkers)
            && $allSequentialLabels
            && $exactExplicitMarkerMatches === 0;
        $trailingImageOffset = $useTrailingSequentialAlignment
            ? max(0, count($facsimiles) - count($normalizedMarkers))
            : 0;

        $pages = [];
        $firstMarker = $normalizedMarkers[0] ?? null;
        if ($firstMarker && !$useTrailingSequentialAlignment) {
            $firstStart = min(max(0, (int) ($firstMarker['resolved_char_index'] ?? 0)), $textLength);
            $firstCode = $this->readerImageCode($firstMarker['image_code'] ?? null);
            if ($firstStart > 0 && $firstCode) {
                $leadingImage = null;
                foreach ($imagesByCode as $code => $image) {
                    if ($code < $firstCode) {
                        $leadingImage = $image;
                    }
                }

            if ($leadingImage) {
                if ($firstStart > 0) {
                    $pages[] = [
                        'label' => 'Avant ' . (($firstMarker['page'] ?? $firstCode) ?: 'le premier repère'),
                        'image' => $leadingImage,
                        'start' => 0,
                        'end' => $firstStart,
                        'line' => null,
                        'imageCode' => $this->readerImageCode($leadingImage['image_code'] ?? $leadingImage['name'] ?? null),
                        'anchorOffset' => null,
                        'anchorPhrase' => null,
                    ];
                }
            }
        }
        }

        $count = count($normalizedMarkers);
        for ($index = 0; $index < $count; $index++) {
            $marker = $normalizedMarkers[$index];
            $start = min(max(0, (int) ($marker['resolved_char_index'] ?? 0)), $textLength);
            $nextMarker = $normalizedMarkers[$index + 1] ?? null;
            $end = $nextMarker
                ? min(max($start, (int) ($nextMarker['resolved_char_index'] ?? 0)), $textLength)
                : $textLength;

            if ($useTrailingSequentialAlignment) {
                $image = $facsimiles[$trailingImageOffset + $index] ?? null;
                $imageCode = $this->readerImageCode($image['image_code'] ?? $image['name'] ?? null);
            } else {
                $imageCode = $this->readerImageCode($marker['image_code'] ?? null);
                $image = $imageCode && array_key_exists($imageCode, $imagesByCode)
                    ? $imagesByCode[$imageCode]
                    : null;
            }
            $label = trim((string) ($marker['page'] ?? '')) ?: ($imageCode ? 'p. ' . $imageCode : 'Repère ' . ($index + 1));
            $excerptStart = $start;
            $excerptEnd = $end;
            $anchorPhrase = trim((string) ($marker['phrase'] ?? ''));

            $pages[] = [
                'label' => $label,
                'image' => $image,
                'start' => $excerptStart,
                'end' => $excerptEnd,
                'line' => isset($marker['line']) ? (int) $marker['line'] : null,
                'imageCode' => $imageCode,
                'anchorOffset' => max(0, $start - $excerptStart),
                'anchorPhrase' => $anchorPhrase !== '' ? $anchorPhrase : null,
            ];
        }

        return $pages;
    }

    private function materializeReaderPage(array $pagePlan, ?string $text): array
    {
        $page = $pagePlan;
        $sourceText = is_string($text) ? $text : '';
        if ($sourceText === '') {
            $page['text'] = '';
            return $page;
        }

        $start = max(0, (int) ($pagePlan['start'] ?? 0));
        $end = max($start, (int) ($pagePlan['end'] ?? $start));
        $segment = mb_substr($sourceText, $start, max(0, $end - $start), 'UTF-8');
        if (($pagePlan['guessed'] ?? false) === true) {
            $trimmed = trim($segment);
            $page['text'] = $trimmed !== '' ? $trimmed : $segment;
            return $page;
        }

        $page['text'] = $segment;
        return $page;
    }

    private function readerGuessedPagePlans(string $text, array $facsimiles): array
    {
        $count = count($facsimiles);
        if ($count === 0) {
            return [];
        }

        $length = mb_strlen($text, 'UTF-8');
        if ($length === 0) {
            return [];
        }

        $pages = [];
        $start = 0;

        for ($index = 0; $index < $count; $index++) {
            $image = $facsimiles[$index] ?? null;
            $imageCode = $this->readerImageCode($image['image_code'] ?? $image['name'] ?? null);

            if ($index === $count - 1) {
                $end = $length;
            } else {
                $targetEnd = (int) round((($index + 1) * $length) / $count);
                $end = $this->readerNextBoundary($text, $targetEnd);
                if ($end <= $start) {
                    $end = min($length, max($start + 1, $targetEnd));
                }
            }

            $label = $imageCode ? 'p. ' . $imageCode : 'Page ' . ($index + 1);

            $pages[] = [
                'label' => $label,
                'image' => $image,
                'start' => $start,
                'end' => $end,
                'line' => null,
                'imageCode' => $imageCode,
                'anchorOffset' => null,
                'anchorPhrase' => null,
                'guessed' => true,
            ];

            $start = $end;
        }

        return $pages;
    }

    private function readerNextBoundary(string $text, int $position): int
    {
        $length = mb_strlen($text, 'UTF-8');
        $position = max(0, min($position, $length));
        if ($position >= $length) {
            return $length;
        }

        $after = mb_substr($text, $position, null, 'UTF-8');
        $doubleBreak = mb_strpos($after, "\n\n", 0, 'UTF-8');
        if ($doubleBreak !== false && $doubleBreak <= 600) {
            return min($length, $position + (int) $doubleBreak + 2);
        }

        $singleBreak = mb_strpos($after, "\n", 0, 'UTF-8');
        if ($singleBreak !== false && $singleBreak <= 300) {
            return min($length, $position + (int) $singleBreak + 1);
        }

        return $position;
    }

    private function readerImageCode(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/_(\d+)(?:_thumb)?\.(?:jpe?g|png)$/i', $raw, $m)) {
            return str_pad((string) ((int) $m[1]), 3, '0', STR_PAD_LEFT);
        }

        if (preg_match('/(\d+)/', $raw, $m)) {
            return str_pad((string) ((int) $m[1]), 3, '0', STR_PAD_LEFT);
        }

        return null;
    }

    private function humanReadableSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 o';
        }

        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1, '.', ' ') . ' ' . $units[$index];
    }

    public function facsimilesProgress(Version $version): JsonResponse
    {
        $version->loadMissing('work.author');
        return response()->json($this->facsimileStatus($version));
    }

    private function facsimilePaths(Version $version): array
    {
        $authorFolder  = $version->work->author->folder;
        $workFolder    = $version->work->folder;
        $versionFolder = $version->folder;

        $sourcePrefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk         = Storage::disk('public');

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
            'source_files'  => $files,
            'dest_dir'      => $destDir,
        ];
    }

    private function listLegacyFacsimileFiles(string $dir): array
    {
        if (!File::isDirectory($dir)) {
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

        $legacySourceDir = base_path('../variance/' . $sourcePrefix);
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
            $queueAbsolute = storage_path('app/' . trim($queuePrefix, '/'));
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
        return storage_path('app/private/facsimile_cancel/' . $versionId . '.flag');
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
        $backupRoot = storage_path('app/private/facsimile_backups/' . $versionId);
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

    private function publishManifestsForVersion(Version $version): array
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder   = $version->work->folder ?? null;
        if (!$authorFolder || !$workFolder) {
            return [];
        }

        $defaultEntries = $version->collectManifestEntries();
        $comparisons = Comparison::where('source_id', $version->id)
            ->orWhere('target_id', $version->id)
            ->get();

        $results = [];
        foreach ($comparisons as $comparison) {
            $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));

            if ($comparison->source_id === $version->id) {
                $entries = $this->resolveManifestEntries($version, $comparison, 'source', $defaultEntries);
                if (!empty($entries)) {
                    $info = $this->writeManifest($authorFolder, $workFolder, $version->folder, 'source', $baseName, $entries);
                    $results[] = ['comparison_id' => $comparison->id, 'type' => 'source'] + $info;
                }
            }

            if ($comparison->target_id === $version->id) {
                $entries = $this->resolveManifestEntries($version, $comparison, 'target', $defaultEntries);
                if (!empty($entries)) {
                    $info = $this->writeManifest($authorFolder, $workFolder, $version->folder, 'target', $baseName, $entries);
                    $results[] = ['comparison_id' => $comparison->id, 'type' => 'target'] + $info;
                }
            }
        }

        return $results;
    }

    private function formatManifestComparison(Version $version, Comparison $comparison, string $role, array $fallbackNames): array
    {
        $meta = $this->readManifestMetadata($version, $comparison, $role, $fallbackNames);

        $sourceName = $comparison->sourceVersion?->name ?? "Version {$comparison->source_id}";
        $targetName = $comparison->targetVersion?->name ?? "Version {$comparison->target_id}";
        $roleLabel  = $role === 'source' ? 'Source' : 'Cible';

        return [
            'comparison_id'     => $comparison->id,
            'comparison_label'  => sprintf('#%d · %s → %s', $comparison->id, $sourceName, $targetName),
            'comparison_folder' => $comparison->folder,
            'role'              => $role,
            'role_label'        => $roleLabel,
            'read_only'         => (bool) ($version->is_legacy || $version->work?->is_legacy || $comparison->is_legacy),
            'selected'          => $meta['selected'],
            'count'             => $meta['count'],
            'exists'            => $meta['exists'],
            'inferred'          => $meta['inferred'],
            'file'              => $meta['file'],
            'updated_at'        => $meta['updated_at'],
        ];
    }

    private function manifestRelativePath(Version $version, Comparison $comparison, string $role): ?string
    {
        $role = strtolower($role);
        if (!in_array($role, ['source', 'target'], true)) {
            return null;
        }

        $version->loadMissing('work.author', 'work');
        $authorFolder  = $version->work->author->folder ?? null;
        $workFolder    = $version->work->folder ?? null;
        $versionFolder = $version->folder ?? null;

        if (!$authorFolder || !$workFolder || !$versionFolder) {
            return null;
        }

        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));
        $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $filename    = sprintf('images_%s_%s.json', $role, $baseName);

        return "{$relativeDir}/{$filename}";
    }

    private function readManifestMetadata(Version $version, Comparison $comparison, string $role, array $fallbackNames = []): array
    {
        $relativePath = $this->manifestRelativePath($version, $comparison, $role);
        if (!$relativePath) {
            return [
                'exists'     => false,
                'selected'   => $fallbackNames,
                'count'      => count($fallbackNames),
                'file'       => null,
                'updated_at' => null,
                'inferred'   => !empty($fallbackNames),
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
            $legacyPath = base_path('../variance/' . $relativePath);
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
                    if (!is_array($entry)) {
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
        if (!$exists && !empty($fallbackNames)) {
            $selected = $fallbackNames;
            $inferred = true;
        }

        return [
            'exists'     => $exists,
            'selected'   => $selected,
            'count'      => count($selected),
            'file'       => $exists ? $relativePath : null,
            'updated_at' => $updatedAt,
            'inferred'   => $inferred,
        ];
    }

    private function readManifestEntries(Version $version, Comparison $comparison, string $role): ?array
    {
        $relativePath = $this->manifestRelativePath($version, $comparison, $role);
        if (!$relativePath) {
            return null;
        }

        $disk = Storage::disk('public');
        $raw = null;
        if ($disk->exists($relativePath)) {
            $raw = $disk->get($relativePath);
        } else {
            $legacyPath = base_path('../variance/' . $relativePath);
            if (is_file($legacyPath)) {
                $raw = File::get($legacyPath);
            }
        }

        if ($raw === null) {
            return null;
        }

        $entries = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($entries)) {
            return null;
        }

        $filtered = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry['big'])) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
    }

    private function resolveManifestEntries(Version $version, Comparison $comparison, string $role, array $fallbackEntries = []): array
    {
        $existing = $this->readManifestEntries($version, $comparison, $role);
        if (is_array($existing) && !empty($existing)) {
            return $existing;
        }

        if (!empty($fallbackEntries)) {
            return $fallbackEntries;
        }

        return $version->collectManifestEntries();
    }

    private function buildManifestEntryFromName(Version $version, string $fileName): ?array
    {
        $version->loadMissing('work.author', 'work');
        $authorFolder  = $version->work->author->folder ?? null;
        $workFolder    = $version->work->folder ?? null;
        $versionFolder = $version->folder ?? null;

        if (!$authorFolder || !$workFolder || !$versionFolder) {
            return null;
        }

        $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk = Storage::disk('public');
        $imageRel = "{$relativeDir}/{$fileName}";
        if (!$disk->exists($imageRel)) {
            return null;
        }
        $thumbRel = preg_replace('/(\.\w+)$/', '_thumb$1', $imageRel);
        $smallRel = $disk->exists($thumbRel) ? $thumbRel : $imageRel;

        return [
            'small' => '/' . ltrim($smallRel, '/'),
            'big'   => '/' . ltrim($imageRel, '/'),
        ];
    }

    private function writeManifest(string $authorFolder, string $workFolder, string $versionFolder, string $type, string $baseName, array $entries): array
    {
        $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $filename    = sprintf('images_%s_%s.json', $type, $baseName);
        $payload     = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Storage::disk('public')->put("{$relativeDir}/{$filename}", $payload);
        $this->mirrorToLegacy($relativeDir, $filename, $payload);

        return ['file' => "{$relativeDir}/{$filename}", 'count' => count($entries)];
    }

    private function mirrorToLegacy(string $relativeDir, string $fileName, string $contents): void
    {
        $legacyDir = base_path('../variance/' . $relativeDir);
        if (!is_dir($legacyDir)) {
            File::makeDirectory($legacyDir, 0775, true, true);
        }
        File::put($legacyDir . DIRECTORY_SEPARATOR . $fileName, $contents);
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
        if (!auth()->check()) {
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
