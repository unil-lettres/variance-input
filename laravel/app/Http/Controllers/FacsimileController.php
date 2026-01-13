<?php
// app/Http/Controllers/FacsimileController.php

namespace App\Http\Controllers;

use App\Jobs\ProcessFacsimileImage;
use App\Models\Version;
use App\Services\PageMarkerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FacsimileController extends Controller
{
    public function __construct(private PageMarkerService $pageMarkerService)
    {
    }

    /**
     * POST /api/upload_facsimiles
     */
    public function store(Request $request)
    {
        @ini_set('memory_limit', config('variance.facsimile_memory_limit', '512M'));
        @set_time_limit(300);
        $validated = $request->validate([
            'version_id' => 'required|exists:versions,id',
            'images.*'   => 'required|image|max:12000',           // 12 Mo
        ]);

        // -----------------------------------------------------------------
        // 1. Construire le chemin cible
        // -----------------------------------------------------------------
        $version = Version::with('work.author')->find($validated['version_id']);
        $work    = $version->work;
        $author  = $work->author;
        if ($version->is_legacy || $work->is_legacy) {
            return response()->json([
                'error' => 'Les versions legacy sont en lecture seule.',
            ], 403);
        }

        $dirRel  = "uploads/{$author->folder}/{$work->folder}/{$version->folder}";
        $disk    = Storage::disk('public');           // = storage/app/public

        if ($request->boolean('reset')) {
            $disk->deleteDirectory($dirRel);
            $legacyDir = base_path('../variance/' . $dirRel);
            if (is_dir($legacyDir)) {
                File::deleteDirectory($legacyDir);
            }

            collect($disk->files($dirRel))
                ->filter(fn ($path) => str_contains(basename($path), 'images_'))
                ->each(fn ($path) => $disk->delete($path));
        }
        $disk->makeDirectory($dirRel);

        $queueDiskName = 'local';
        $queueDisk     = Storage::disk($queueDiskName);
        $queueDir      = "facsimile_queue/{$author->folder}/{$work->folder}/{$version->folder}";
        if ($request->boolean('reset')) {
            $queueDisk->deleteDirectory($queueDir);
        }
        $queueDisk->makeDirectory($queueDir);

        // Point de départ : numéro suivant en se basant sur les fichiers déjà présents
        $existingIndexes = collect($disk->files($dirRel))
            ->map(fn ($path) => basename($path))
            ->map(function ($name) use ($version) {
                if (preg_match('/^img_' . preg_quote($version->folder, '/') . '_(\d+)\.(?:jpe?g|png)$/i', $name, $m)) {
                    return (int) $m[1];
                }
                return null;
            })
            ->filter();

        $queuedIndexes = collect($queueDisk->files($queueDir))
            ->map(fn ($path) => basename($path))
            ->map(function ($name) use ($version) {
                if (preg_match('/^img_' . preg_quote($version->folder, '/') . '_(\d+)\./i', $name, $m)) {
                    return (int) $m[1];
                }
                return null;
            })
            ->filter();

        $existingIndexes = $existingIndexes
            ->merge($queuedIndexes)
            ->unique()
            ->values();

        $startIndex = ($existingIndexes->max() ?? 0) + 1;

        // -----------------------------------------------------------------
        // 2. Boucle de traitement
        // -----------------------------------------------------------------
        $i       = $startIndex;
        $queued  = 0;
        $errors  = [];
        // Assure deterministic processing order: original client filenames (natural, case‑insensitive)
        $images = $validated['images'] ?? [];
        usort($images, function ($a, $b) {
            return strnatcasecmp($a->getClientOriginalName(), $b->getClientOriginalName());
        });

        $maxLongEdge  = max(1, (int) config('variance.facsimile_max_long_edge', 2400));
        $mainQuality  = max(10, min(100, (int) config('variance.facsimile_main_quality', 85)));
        $thumbWidth   = max(1, (int) config('variance.facsimile_thumb_width', 200));
        $thumbQuality = max(10, min(100, (int) config('variance.facsimile_thumb_quality', 80)));

        foreach ($images as $file) {
            $index    = str_pad($i, 3, '0', STR_PAD_LEFT);
            $basename = "img_{$version->folder}_{$index}";
            $ext      = strtolower($file->getClientOriginalExtension() ?: $file->guessClientExtension() ?: 'upload');
            $ext      = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'upload';

            $queuedFilename = "{$basename}.{$ext}";
            $queuedPath     = trim($queueDir . '/' . $queuedFilename, '/');

            try {
                $queueDisk->delete($queuedPath);
                $queueDisk->putFileAs($queueDir, $file, $queuedFilename);

                ProcessFacsimileImage::dispatch(
                    versionId: $version->id,
                    basename: $basename,
                    queuedDisk: $queueDiskName,
                    queuedPath: $queuedPath,
                    outputDir: $dirRel,
                    maxLongEdge: $maxLongEdge,
                    mainQuality: $mainQuality,
                    thumbWidth: $thumbWidth,
                    thumbQuality: $thumbQuality,
                );

                $queued++;
            } catch (\Throwable $e) {
                Log::warning('Facsimile enqueue failed', [
                    'file' => $file->getClientOriginalName(),
                    'err'  => $e->getMessage(),
                ]);
                $errors[] = "{$basename}.jpg";
                $queueDisk->delete($queuedPath);
            }
            $i++;
        }

        return response()->json([
            'status'      => $queued ? 'queued' : 'error',
            'stored_in'   => $dirRel,
            'files_added' => $queued,
            'errors'      => $errors,
            'files_report'=> [],
            'processing'  => (bool) $queued,
            'limits'      => [
                'max_long_edge' => $maxLongEdge,
                'main_quality'  => $mainQuality,
                'thumb_width'   => $thumbWidth,
            ],
        ]);
    }

    private function refreshComparisonMarkers(Version $version): void
    {
        // Default markers are no longer injected; comparisons will only show
        // facsimiles when real pagination/_lignes exists and images are published.
    }

    /**
     * GET /api/facsimiles?version_id=…
     */
    public function index(Request $request)
    {
        $request->validate(['version_id' => 'required|exists:versions,id']);

        $version = Version::with('work.author')->findOrFail($request->version_id);
        $work    = $version->work;
        $author  = $work->author;

        // Dossier relatif (dans storage/app/public)
        $dirRel = "uploads/{$author->folder}/{$work->folder}/{$version->folder}";
        $disk   = Storage::disk('public');
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
            return response()->json([]);          // aucun fichier
        }

        $images = $all
            ->filter(fn ($entry) => preg_match('/\.(jpe?g|png)$/i', $entry['name']) && ! str_contains($entry['name'], '_thumb'))
            ->values()
            ->map(function ($entry) use ($disk, $dirRel, $legacyDir, $useLegacy) {

                // chemin miniature : img_*_thumb.jpg
                $thumbName  = preg_replace('/(\.\w+)$/', '_thumb$1', $entry['name']);
                $thumbPath  = $useLegacy ? $legacyDir . '/' . $thumbName : $dirRel . '/' . $thumbName;
                $thumbExist = $useLegacy ? is_file($thumbPath) : $disk->exists($thumbPath);

                $sizeBytes = is_file($entry['absolute']) ? filesize($entry['absolute']) : 0;
                $width     = null;
                $height    = null;
                if (is_file($entry['absolute'])) {
                    $info = @getimagesize($entry['absolute']);
                    if (is_array($info)) {
                        $width  = $info[0] ?? null;
                        $height = $info[1] ?? null;
                    }
                }

                $bigUrl = $useLegacy
                    ? legacy_url($dirRel . '/' . $entry['name'])
                    : admin_url('storage/' . ltrim($entry['path'], '/'));
                $thumbUrl = null;
                if ($thumbExist) {
                    $thumbUrl = $useLegacy
                        ? legacy_url($dirRel . '/' . $thumbName)
                        : admin_url('storage/' . ltrim($thumbPath, '/'));
                }

                return [
                    'name'       => $entry['name'],
                    'big'        => $bigUrl,
                    'thumb'      => $thumbUrl,
                    'hasThumb'   => $thumbExist,
                    'size_bytes' => (int) $sizeBytes,
                    'size_human' => $this->humanReadableSize((int) $sizeBytes),
                    'width'      => $width,
                    'height'     => $height,
                ];
            });

        return response()->json($images);
    }

    /**
     * GET /api/facsimiles/space
     */
    public function freeSpace(Request $request)
    {
        $required = (int) $request->query('required_bytes', 0);
        $required = max(0, $required);

        $path = storage_path('app');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de calculer l’espace disque disponible.',
            ], 500);
        }

        $minFree = 1024 * 1024 * 1024; // 1 GiB safety margin
        $remaining = $free - $required;
        $ok = $remaining >= $minFree;

        return response()->json([
            'status' => 'ok',
            'path' => $path,
            'free_bytes' => $free,
            'total_bytes' => $total,
            'required_bytes' => $required,
            'min_free_bytes' => $minFree,
            'remaining_bytes' => $remaining,
            'ok' => $ok,
        ]);
    }

    private function humanReadableSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 o';
        }

        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $idx   = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $idx < count($units) - 1) {
            $value /= 1024;
            $idx++;
        }

        $precision = $idx === 0 ? 0 : 1;
        return number_format($value, $precision, ',', ' ') . ' ' . $units[$idx];
    }
}
