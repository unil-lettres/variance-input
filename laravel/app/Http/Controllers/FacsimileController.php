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

class FacsimileController extends Controller
{
    /**
     * POST /api/upload_facsimiles
     */
    public function store(Request $request)
    {
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
        foreach ($validated['images'] as $file) {
            $index    = str_pad($i, 3, '0', STR_PAD_LEFT);
            $basename = "img_{$version->folder}_{$index}";
            $ext      = 'jpg';

            $mainPath  = "{$dirRel}/{$basename}.{$ext}";
            $thumbPath = "{$dirRel}/{$basename}_thumb.jpg";

            // 1) Copie l’original (toujours)
            $disk->putFileAs($dirRel, $file, basename($mainPath));
            $added++;      // ← on compte tout de suite
            $i++;

            // 2) Essaie de faire la miniature – mais sans bloquer l’import
            try {
                $manager = app(ImageManager::class);

                $image = $manager->read($file->get());

                $thumbImg = $image
                    ->scale(200)
                    ->encode(new JpegEncoder(quality: 80));

                $disk->put($thumbPath, (string) $thumbImg);

            } catch (\Throwable $e) {
                Log::warning('Miniature fac-similé échouée', [
                    'file' => $file->getClientOriginalName(),
                    'err'  => $e->getMessage(),
                ]);
                $errors[] = "{$basename}.{$ext}";
            }
        }


        return response()->json([
            'status'      => $added ? 'ok' : 'error',
            'stored_in'   => $dirRel,
            'files_added' => $added,
            'errors'      => $errors,
        ]);
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

            return [
                'name'      => basename($p),
                'big'       => '/storage/'.$p,                    // ✅ URL publique
                'thumb'     => $thumbExist ? '/storage/'.$thumbPath : null,
                'hasThumb'  => $thumbExist,
            ];
        });

    return response()->json($images);
}


}
