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

class VersionController extends Controller
{
    private const INLINE_TAG_WHITELIST = ['pb'];
    private const USER_IMPORT_OPTION_KEYS = [
        'strip_indentation',
        'collapse_double_spaces',
        'trim_line_ends',
        'trim_file_edges',
        'preserve_nbsp',
        'legacy_typography',
    ];
    private const DEFAULT_IMPORT_OPTIONS = [
        'strip_indentation'      => true,
        'collapse_double_spaces' => false,
        'trim_line_ends'         => true,
        'trim_file_edges'        => true,
        'strip_invisible_chars'  => true,
        'normalize_line_endings' => true,
        'preserve_nbsp'          => false,
        'legacy_typography'      => false,
    ];

    public function __construct(private PageMarkerService $pageMarkerService)
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
        $versionsInUse = empty($versionIds)
            ? []
            : Comparison::query()
                ->whereIn('source_id', $versionIds)
                ->orWhereIn('target_id', $versionIds)
                ->get(['source_id', 'target_id'])
                ->flatMap(fn (Comparison $comparison) => [$comparison->source_id, $comparison->target_id])
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->all();

        $inUseLookup = array_fill_keys($versionsInUse, true);

        return $versions
            ->map(function (Version $version) use ($inUseLookup) {
                $lignesInfo = $this->pageMarkerService->getLignesInfo($version->id);
                if ($lignesInfo) {
                    $lignesInfo['url'] = admin_url("api/versions/{$version->id}/lignes");
                }
                $paginationInfo = $this->pageMarkerService->getPaginationInfo($version->id);
                $pageMarkerProgress = $version->is_legacy
                    ? null
                    : $this->pageMarkerService->getProgressSnapshot($version->id);
                $textLength = null; // lazy-loaded via /api/versions/{id}/text-length
                $facsimileCacheKey = "versions:facsimiles:{$version->id}";
                $facsimileCacheTtl = now()->addSeconds(20);
                $facsimiles = Cache::remember(
                    $facsimileCacheKey,
                    $facsimileCacheTtl,
                    fn () => $this->facsimileStatus($version)
                );
                return [
                    'id'         => $version->id,
                    'name'       => $version->name,
                    'folder'     => $version->folder,
                    'work_id'    => $version->work_id,
                    'is_legacy'  => (bool) $version->is_legacy,
                    'is_in_use'  => isset($inUseLookup[$version->id]),
                    'pagination_done' => (bool) $version->pagination_done,
                    'pagination_done_at' => $version->pagination_done_at?->getTimestamp(),
                    'pagination_done_by' => $version->pagination_done_by,
                    'pagination_done_by_name' => $version->paginationDoneBy?->name,
                    'pb_markers' => $this->pageMarkerService->countPbMarkers($version),
                    'xml_available' => is_file($version->getXMLFilePath()),
                    'text_available' => is_file(storage_path("app/public/uploads/versions/{$version->folder}.txt")),
                    'text_length' => $textLength,
                    'facsimiles' => $facsimiles,
                    'page_markers' => $this->pageMarkerService->countMarkers($version),
                    'page_marker_progress' => $pageMarkerProgress,
                    'lignes' => $lignesInfo,
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
        $options = $this->resolveImportOptions($request);
        $fullTxt = storage_path("app/public/{$txtStoragePath}");
        $useLegacyTxt2Tei = $this->useLegacyTxt2TeiImport();
        $utf8    = $this->readFileAsUtf8(
            $fullTxt,
            $request->input('original_encoding'),
            $useLegacyTxt2Tei ? false : $options['normalize_line_endings']
        );
        if (!$this->isLikelyTextContent($utf8)) {
            throw ValidationException::withMessages([
                'versionFile' => 'Le fichier importé ne semble pas être un texte lisible.',
            ]);
        }
        $teiTitle = $work->title ?: $validated['name'];
        $teiAuthor = $work->author?->name ?: '';

        if ($useLegacyTxt2Tei) {
            $tei = $this->buildLegacyTxt2TeiXml($utf8, $teiTitle, $nextNumber, $teiAuthor, $validated['name']);
        } else {
            if ($options['strip_invisible_chars']) {
                $utf8 = $this->stripInvisibleCharacters($utf8, $options['preserve_nbsp']);
            }
            if ($options['legacy_typography']) {
                $utf8 = $this->applyLegacyTypographicNormalisation($utf8);
            }
            $utf8 = $this->removeTabs($utf8);
            if ($options['strip_indentation']) {
                $utf8 = $this->stripLeadingIndentation($utf8);
            }
            if ($options['trim_line_ends']) {
                $utf8 = $this->trimLineEndSpaces($utf8);
            }
            if ($options['collapse_double_spaces']) {
                $utf8 = $this->collapseDoubleSpaces($utf8);
            }
            if ($options['trim_file_edges']) {
                $utf8 = $this->trimFileEdges($utf8);
            }

            /* 5. Insert line‑break tags */
            $lines        = preg_split('/\r\n|\r|\n/', $utf8) ?: [''];
            $escapedLines = array_map(fn($l) => htmlspecialchars($l, ENT_XML1 | ENT_COMPAT, 'UTF-8'), $lines);
            $bodyWithLb   = implode("\n          <lb/>\n", $escapedLines);
            $bodyWithLb   = $this->restoreInlineTags($bodyWithLb, self::INLINE_TAG_WHITELIST);

            /* 6. TEI skeleton */
            $xmlId = 'v' . $nextNumber . preg_replace('/[^A-Za-z0-9]/', '', strtolower($shortTitle));
            $headerXml = $this->buildTeiHeaderXml($teiTitle, $teiAuthor, $validated['name']);
            $tei = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
                   "<TEI xml:id=\"{$xmlId}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n".
                   $headerXml .
                   "  <text>\n    <body>\n      <div>\n        <p>\n          {$bodyWithLb}\n        </p>\n      </div>\n    </body>\n  </text>\n</TEI>";
        }

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

        // Remove DB record regardless of file presence
        $version->delete();
        Cache::forget("versions:index:work:{$version->work_id}");

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

    /**
     * Toggle the ignored status of a facsimile page.
     */
    public function toggleIgnoredPage(Version $version, Request $request): JsonResponse
    {
        $this->assertVersionEditable($version);
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

    private function resolveImportOptions(Request $request): array
    {
        $resolved = self::DEFAULT_IMPORT_OPTIONS;

        foreach (self::USER_IMPORT_OPTION_KEYS as $key) {
            if ($request->has($key)) {
                $resolved[$key] = $request->boolean($key);
            }
        }

        return $resolved;
    }

    private function useLegacyTxt2TeiImport(): bool
    {
        $value = env('TXT_IMPORT_MODE');
        if (is_string($value) && strtolower($value) === 'laravel') {
            return false;
        }

        return app()->isLocal();
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

        $escapedText = htmlspecialchars($txt, ENT_XML1 | ENT_COMPAT, 'UTF-8');

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

    /** Remove invisible/spacing control characters while preserving text flow. */
    private function stripInvisibleCharacters(string $txt, bool $preserveNbsp = false): string
    {
        $search = [
            "\u{2002}", "\u{2003}", "\u{2009}",
            "\u{200B}", "\u{FEFF}",
        ];
        $replace = [
            ' ', ' ', ' ',
            '', '',
        ];

        if (!$preserveNbsp) {
            array_unshift($search, "\u{00A0}", "\u{202F}");
            array_unshift($replace, ' ', ' ');
        }

        return str_replace($search, $replace, $txt);
    }

    /**
     * Optional typographic normalisation aligned with protocol rules 1–5:
     * 1) remove spaces before ; : ! ?
     * 2) normalize suspension points to ellipsis character
     * 3) normalize apostrophes to typographic apostrophe
     * 4) normalize only unambiguous dash variants to en dash
     * 5) normalize guillemets spacing and common quote variants (best effort)
     */
    private function applyLegacyTypographicNormalisation(string $txt): string
    {
        // 3) Apostrophes
        $txt = str_replace(
            ["\u{2018}", "\u{2019}", "\u{02BC}", "\u{02B9}", "\u{00B4}", "\u{02C8}", "'"],
            "\u{2019}",
            $txt
        );

        // 2) Points de suspension: every run of 3 dots becomes one ellipsis char.
        $txt = preg_replace('/\.{3}/u', "\u{2026}", $txt) ?? $txt;

        // 1) No space before ; : ! ?
        $txt = preg_replace('/[ \t\x{00A0}\x{202F}]+([;:!?])/u', '$1', $txt) ?? $txt;

        // 4) Conservative dash normalization:
        // convert only unambiguous long/minus variants to en dash.
        // Keep hyphen-like codepoints untouched to avoid corrupting compounds/cesurae.
        $txt = str_replace(
            ["\u{2014}", "\u{2212}"],
            "\u{2013}",
            $txt
        );

        // 5) Guillemets and spacing around them.
        $txt = str_replace(["\u{2039}", "\u{203A}"], ["\u{00AB}", "\u{00BB}"], $txt);
        $txt = preg_replace('/«\s+/u', '«', $txt) ?? $txt;
        $txt = preg_replace('/\s+»/u', '»', $txt) ?? $txt;

        // Best effort: map paired curly quotes to guillemets.
        $txt = preg_replace('/“([^”\r\n]+)”/u', '«$1»', $txt) ?? $txt;
        $txt = preg_replace('/„([^”\r\n]+)”/u', '«$1»', $txt) ?? $txt;

        return $txt;
    }

    /** Remove tabs globally (editorial policy). */
    private function removeTabs(string $txt): string
    {
        return str_replace("\t", '', $txt);
    }

    /** Remove tabs/spaces at line starts (paragraph indentation). */
    private function stripLeadingIndentation(string $txt): string
    {
        return preg_replace('/^[ \t]+/m', '', $txt) ?? $txt;
    }

    /** Remove spaces/tabs before line breaks. */
    private function trimLineEndSpaces(string $txt): string
    {
        return preg_replace('/[ \t]+$/m', '', $txt) ?? $txt;
    }

    /** Optional compression of inter-word space runs. */
    private function collapseDoubleSpaces(string $txt): string
    {
        return preg_replace('/ {2,}/', ' ', $txt) ?? $txt;
    }

    /** Remove leading/trailing spaces and blank lines at file edges. */
    private function trimFileEdges(string $txt): string
    {
        return trim($txt);
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

    /**
     * Re-introduce whitelisted inline tags (e.g. <pb/>) that were escaped when
     * generating the TEI body. Keeps the rest of the text entity-encoded to
     * avoid arbitrary markup from uploads.
     *
     * @param  string $text
     * @param  array<int, string> $allowedTags
     */
    private function restoreInlineTags(string $text, array $allowedTags): string
    {
        if (empty($allowedTags)) {
            return $text;
        }

        $alternation = implode('|', array_map(static fn ($tag) => preg_quote($tag, '/'), $allowedTags));
        $pattern = '/&lt;\/?(?:' . $alternation . ')(?:\s+.*?)?\/?&gt;/si';

        return preg_replace_callback($pattern, static function ($match) {
            return html_entity_decode($match[0], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }, $text);
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
        if ($work->is_legacy) {
            abort(403, 'Cette œuvre est en lecture seule.');
        }
    }

    private function assertVersionEditable(Version $version): void
    {
        $version->loadMissing('work');

        if ($version->is_legacy || $version->work?->is_legacy) {
            abort(403, 'Cette version est en lecture seule.');
        }
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
