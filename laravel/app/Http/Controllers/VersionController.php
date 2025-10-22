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
use App\Services\PageMarkerService;

class VersionController extends Controller
{
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

        $versions = Version::with(['work.author'])
            ->where('work_id', $workId)
            ->get()
            ->map(function (Version $version) {
                $lignesInfo = $this->pageMarkerService->getLignesInfo($version->id);
                if ($lignesInfo) {
                    $lignesInfo['url'] = admin_url("api/versions/{$version->id}/lignes");
                }
                $paginationInfo = $this->pageMarkerService->getPaginationInfo($version->id);
                return [
                    'id'         => $version->id,
                    'name'       => $version->name,
                    'folder'     => $version->folder,
                    'work_id'    => $version->work_id,
                    'facsimiles' => $this->facsimileStatus($version),
                    'page_markers' => $this->pageMarkerService->countMarkers($version),
                    'page_marker_progress' => $this->pageMarkerService->getProgressSnapshot($version->id),
                    'lignes' => $lignesInfo,
                    'pagination' => $paginationInfo,
                ];
            });

        return response()->json($versions, 200);
    }

    /** Upload → save .txt untouched → generate TEI (UTF‑8 LF) */
    public function store(Request $request)
    {
        /* 1. Validate */
        $validated = $request->validate([
            'work_id'     => 'required|exists:works,id',
            'name'        => 'required|string|max:100',
            'versionFile' => 'required|file|mimetypes:text/plain|max:8192',
        ]);

        /* 2. Context */
        $work        = Work::findOrFail($validated['work_id']);
        $shortTitle  = $work->short_title;
        $nextNumber  = Version::where('work_id', $work->id)->count() + 1;
        $baseName    = "{$nextNumber}{$shortTitle}";
        $folderPath  = 'uploads/versions'; // storage/app/public

        /* 3. Persist raw .txt (no conversion) */
        $txtFilename    = "{$baseName}.txt";
        $txtStoragePath = "{$folderPath}/{$txtFilename}";
        $request->file('versionFile')->storeAs($folderPath, $txtFilename, 'public');

        /* 4. Read & normalise → UTF‑8 LF */
        $fullTxt = storage_path("app/public/{$txtStoragePath}");
        $utf8    = $this->readFileAsUtf8($fullTxt, $request->input('original_encoding'));
        $utf8    = $this->normalizeCharacters($utf8);
        $utf8    = $this->collapseSpacesAndTabs($utf8);

        /* 5. Insert line‑break tags */
        $lines        = explode("\n", $utf8);
        $escapedLines = array_map(fn($l) => htmlspecialchars($l, ENT_XML1 | ENT_COMPAT, 'UTF-8'), $lines);
        $bodyWithLb   = implode("\n          <lb/>\n", $escapedLines);

        /* 6. TEI skeleton */
        $xmlId = 'v' . $nextNumber . preg_replace('/[^A-Za-z0-9]/', '', strtolower($shortTitle));
        $tei = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
               "<TEI xml:id=\"{$xmlId}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n".
               "  <teiHeader>\n    <fileDesc>\n      <titleStmt><title>{$validated['name']}</title></titleStmt>\n      <publicationStmt><p>Imported via Variance</p></publicationStmt>\n      <sourceDesc><p>Generated automatically</p></sourceDesc>\n    </fileDesc>\n  </teiHeader>\n  <text>\n    <body>\n      <div>\n        <p>\n          {$bodyWithLb}\n        </p>\n      </div>\n    </body>\n  </text>\n</TEI>";

        /* 7. Save .xml */
        Storage::disk('public')->put("{$folderPath}/{$baseName}.xml", $tei);

        /* 8. DB row */
        $version = Version::create([
            'work_id' => $work->id,
            'name'    => $validated['name'],
            'folder'  => $baseName,
        ]);

        return response()->json([
            'message' => 'Version uploaded successfully!',
            'version' => $version,
        ], 201);
    }

    /** Update name */
    public function update(Request $req, $id)
    {
        $version = Version::findOrFail($id);
        $version->update($req->validate(['name' => 'required|string|max:45']));
        return response()->json($version, 200);
    }

    /** Delete version; tolerate missing files */
    public function destroy($id)
    {
        $version = Version::with('work.author')->findOrFail($id);

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
        $version->loadMissing('work.author');
        $removed    = $this->purgeFacsimileStorage($version, includePublished: true);
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
        $version->loadMissing('work.author');
        $this->pageMarkerService->markCancelled($version->id, 'Annulé par l’utilisateur');

        $info = $this->pageMarkerService->getLignesInfo($version->id);
        if ($info) {
            $info['url'] = admin_url("api/versions/{$version->id}/lignes");
        }
        $progress = $this->pageMarkerService->getProgressSnapshot($version->id);

        return response()->json([
            'status'   => 'cancelled',
            'message'  => 'Traitement des balises de pagination annulé.',
            'lignes'   => $info,
            'progress' => $progress,
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

    public function publishFacsimiles(Request $request)
    {
        $validated = $request->validate([
            'version_id' => 'required|exists:versions,id',
        ]);

        $version = Version::with('work.author')->findOrFail($validated['version_id']);
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
        $request->validate([
            'lignes' => 'required|file|max:4096',
        ]);

        $file     = $request->file('lignes');
        $relative = $this->pageMarkerService->lignesRelativePath($version->id);
        Storage::disk('local')->putFileAs('private/lignes', $file, $version->id . '.txt');
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

    /* ──────────────────────────── HELPERS ──────────────────────────── */

    /** Detect + convert arbitrary bytes to UTF‑8 LF */
    private function readFileAsUtf8(string $absPath, ?string $hint = null): string
    {
        $bytes = file_get_contents($absPath);
        // Choose source encoding
        $enc = null;
        if ($hint) {
            if (stripos($hint, 'UTF-8') !== false) {
                $enc = 'UTF-8';
            } elseif (stripos($hint, 'WINDOWS-1252') !== false || stripos($hint, 'ISO-8859') !== false) {
                $enc = 'Windows-1252';
            }
        }
        $enc ??= mb_detect_encoding($bytes, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252';

        $utf8 = mb_convert_encoding($bytes, 'UTF-8', $enc);
        return str_replace(["\r\n", "\r"], "\n", $utf8);
    }

    /** Unicode tidy‑up (subset of original txt2tei.py) */
    private function normalizeCharacters(string $txt): string
    {
        return str_replace([
            "\u{2013}", "\u{2212}", "\u{2010}", "\u{2011}", "\u{00AD}", "\u{2026}",
            "\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2018}", "\u{2019}", "\u{02BC}", "\u{00B4}", "\u{02C8}",
            "\u{00A0}", "\u{2002}", "\u{2003}", "\u{2009}", "\u{202F}", "\u{200B}", "\u{FEFF}"
        ], [
            "\u{2014}", '-', '-', '-', '', '...',
            '"', '"', '"', '"', "'", "'", "'", "'", "'",
            ' ', ' ', ' ', ' ', ' ', '', ''
        ], $txt);
    }

    /** Collapse runs of spaces/tabs but leave newlines */
    private function collapseSpacesAndTabs(string $txt): string
    {
        $txt = str_replace("\t", ' ', $txt);
        return preg_replace('/ {2,}/', ' ', $txt);
    }

    private function facsimileStatus(Version $version): array
    {
        $paths = $this->facsimilePaths($version);

        $sourceCount = collect($paths['source_files'])
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
                ->filter(fn ($path) => preg_match('/\.(jpe?g|png)$/i', basename($path)))
                ->count()
            : 0;

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

    private function purgeFacsimileStorage(Version $version, bool $includePublished = true): bool
    {
        $version->loadMissing('work.author');
        $removed = false;
        $paths   = $this->facsimilePaths($version);
        $disk    = Storage::disk('public');

        if ($disk->deleteDirectory($paths['source_prefix'])) {
            $removed = true;
        }

        $legacySourceDir = base_path('../variance/' . $paths['source_prefix']);
        if (is_dir($legacySourceDir)) {
            File::deleteDirectory($legacySourceDir);
            $removed = true;
        }

        if ($includePublished && !empty($paths['dest_dir']) && File::isDirectory($paths['dest_dir'])) {
            File::deleteDirectory($paths['dest_dir']);
            $removed = true;
        }

        $queuePrefix = $this->queuePrefix($version);
        $queueDisk   = Storage::disk('local');
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

    private function queuePrefix(Version $version): string
    {
        $authorFolder  = $version->work->author->folder ?? '';
        $workFolder    = $version->work->folder ?? '';
        $versionFolder = $version->folder ?? '';

        return trim(sprintf('facsimile_queue/%s/%s/%s', $authorFolder, $workFolder, $versionFolder), '/');
    }

    private function publishManifestsForVersion(Version $version): array
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder   = $version->work->folder ?? null;
        if (!$authorFolder || !$workFolder) {
            return [];
        }

        $entries = $this->collectManifestEntries($authorFolder, $workFolder, $version->folder);
        if (empty($entries)) {
            return [];
        }

        $comparisons = Comparison::where('source_id', $version->id)
            ->orWhere('target_id', $version->id)
            ->get();

        $results = [];
        foreach ($comparisons as $comparison) {
            $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));

            if ($comparison->source_id === $version->id) {
                $info = $this->writeManifest($authorFolder, $workFolder, $version->folder, 'source', $baseName, $entries);
                $results[] = ['comparison_id' => $comparison->id, 'type' => 'source'] + $info;
            }

            if ($comparison->target_id === $version->id) {
                $info = $this->writeManifest($authorFolder, $workFolder, $version->folder, 'target', $baseName, $entries);
                $results[] = ['comparison_id' => $comparison->id, 'type' => 'target'] + $info;
            }
        }

        return $results;
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

    private function collectManifestEntries(string $authorFolder, string $workFolder, string $versionFolder): array
    {
        $disk = Storage::disk('public');
        $prefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        if (!$disk->exists($prefix)) {
            return [];
        }

        $files = collect($disk->files($prefix))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => preg_match('/\.(jpe?g|png)$/i', $name))
            ->reject(fn ($name) => $this->isThumbnail($name))
            ->sort(fn ($a, $b) => strnatcasecmp($a, $b))
            ->values();

        return $files->map(function ($file) use ($disk, $prefix, $authorFolder, $workFolder, $versionFolder) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $ext  = pathinfo($file, PATHINFO_EXTENSION);
            $thumbName = $base . '_thumb.' . $ext;

            $big   = "/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}/{$file}";
            $small = $disk->exists("{$prefix}/{$thumbName}")
                ? "/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}/{$thumbName}"
                : $big;

            return [
                'small' => $small,
                'big'   => $big,
            ];
        })->toArray();
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
}
