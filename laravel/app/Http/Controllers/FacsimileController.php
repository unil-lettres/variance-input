<?php
// app/Http/Controllers/FacsimileController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

use App\Models\Version;
use App\Services\PageMarkerService;

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

        $startIndex = ($existingIndexes->max() ?? 0) + 1;

        // -----------------------------------------------------------------
        // 2. Boucle de traitement
        // -----------------------------------------------------------------
        $i       = $startIndex;
        $added   = 0;
        $errors  = [];
        // Assure deterministic processing order: original client filenames (natural, case‑insensitive)
        $images = $validated['images'];
        usort($images, function ($a, $b) {
            return strnatcasecmp($a->getClientOriginalName(), $b->getClientOriginalName());
        });

        $manager      = app(ImageManager::class);
        $maxLongEdge  = max(1, (int) config('variance.facsimile_max_long_edge', 2400));
        $mainQuality  = max(10, min(100, (int) config('variance.facsimile_main_quality', 85)));
        $thumbWidth   = max(1, (int) config('variance.facsimile_thumb_width', 200));
        $thumbQuality = max(10, min(100, (int) config('variance.facsimile_thumb_quality', 80)));
        $reports      = [];

        foreach ($images as $file) {
            $index    = str_pad($i, 3, '0', STR_PAD_LEFT);
            $basename = "img_{$version->folder}_{$index}";
            $ext      = 'jpg';

            $mainPath  = "{$dirRel}/{$basename}.{$ext}";
            $thumbPath = "{$dirRel}/{$basename}_thumb.jpg";

            try {
                $original = $manager->read($file->getRealPath());

                $origWidth  = $original->width();
                $origHeight = $original->height();
                $origSize   = $file->getSize() ?? 0;

                $display = clone $original;

                $longEdge = max($origWidth, $origHeight);
                $resized  = false;
                if ($longEdge > $maxLongEdge) {
                    $ratio   = $maxLongEdge / $longEdge;
                    $targetW = max(1, (int) round($origWidth * $ratio));
                    $targetH = max(1, (int) round($origHeight * $ratio));
                    $display->scale($targetW, $targetH);
                    $resized = true;
                }

                $mainEncoded = $display->encode(new JpegEncoder(quality: $mainQuality));
                $disk->put($mainPath, (string) $mainEncoded);

                $storedSize   = $disk->size($mainPath);
                $storedWidth  = $display->width();
                $storedHeight = $display->height();

                $thumbImage = clone $original;
                $thumbImage->scale($thumbWidth);
                $thumbEncoded = $thumbImage->encode(new JpegEncoder(quality: $thumbQuality));
                $disk->put($thumbPath, (string) $thumbEncoded);

                unset($thumbImage, $thumbEncoded, $display, $mainEncoded, $original);
                gc_collect_cycles();

                $reports[] = [
                    'name'             => basename($mainPath),
                    'original_size'    => $origSize,
                    'original_width'   => $origWidth,
                    'original_height'  => $origHeight,
                    'stored_size'      => $storedSize,
                    'stored_width'     => $storedWidth,
                    'stored_height'    => $storedHeight,
                    'resized'          => $resized,
                ];
                $added++;
            } catch (\Throwable $e) {
                Log::warning('Facsimile processing failed', [
                    'file' => $file->getClientOriginalName(),
                    'err'  => $e->getMessage(),
                ]);
                $errors[] = "{$basename}.{$ext}";
            }
            $i++;
        }


        if ($added) {
            $this->refreshComparisonMarkers($version);
        }

        return response()->json([
            'status'      => $added ? 'ok' : 'error',
            'stored_in'   => $dirRel,
            'files_added' => $added,
            'errors'      => $errors,
            'files_report'=> $reports,
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

        if (! $disk->exists($dirRel)) {
            return response()->json([]);          // aucun fichier
        }

        // Liste des fichiers
        $all = collect($disk->files($dirRel));

        $images = $all
            ->filter(fn ($p) => preg_match('/\.(jpe?g|png)$/i', $p) && ! str_contains($p, '_thumb'))
            ->values()
            ->map(function ($p) use ($disk) {

                // chemin miniature : img_*_thumb.jpg
                $thumbPath  = preg_replace('/(\.\w+)$/', '_thumb$1', $p);
                $thumbExist = $disk->exists($thumbPath);

                $sizeBytes = $disk->size($p);
                $width     = null;
                $height    = null;
                $absolute  = $disk->path($p);
                if (is_file($absolute)) {
                    $info = @getimagesize($absolute);
                    if (is_array($info)) {
                        $width  = $info[0] ?? null;
                        $height = $info[1] ?? null;
                    }
                }

                return [
                    'name'       => basename($p),
                    'big'        => admin_url('storage/'.ltrim($p, '/')),
                    'thumb'      => $thumbExist ? admin_url('storage/'.ltrim($thumbPath, '/')) : null,
                    'hasThumb'   => $thumbExist,
                    'size_bytes' => $sizeBytes,
                    'size_human' => $this->humanReadableSize($sizeBytes),
                    'width'      => $width,
                    'height'     => $height,
                ];
            });

        return response()->json($images);
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
