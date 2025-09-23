<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Comparison;

class PublishController extends Controller
{
    public const COMPONENTS = [
        'd.xhtml',
        'i.xhtml',
        'r.xhtml',
        's.xhtml',
        'source.xhtml',
        'target.xhtml',
    ];

    public function publish(Request $request)
    {
        $request->validate(['comparison_id' => 'required|integer']);

        // 1. Récupération de la comparaison --------------------------------
        /** @var Comparison $comparison */
        $comparison = Comparison::findOrFail($request->input('comparison_id'));

        // 2. Récupérer l’œuvre via la version source ------------------------
        try {
            [ $sourceDir, $destDir, $destPath ] = $this->resolvePaths($comparison);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'comparison_id' => $comparison->id,
            ], 422);
        }
        if (!is_dir($destPath)) {
            Storage::disk('public')->makeDirectory($destDir);
        }

        // 5. Copier uniquement les composants XHTML nécessaires -----------
        if (!is_dir($sourceDir)) {
            return response()->json([
                'error' => 'Dossier source introuvable pour cette comparaison.',
                'source_dir' => $sourceDir,
            ], 404);
        }

        $copied = [];
        $missing = [];
        foreach (self::COMPONENTS as $name) {
            $srcFile = $sourceDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($srcFile)) {
                $missing[] = $name;
                continue;
            }

            Storage::disk('public')->put(
                "{$destDir}/{$name}",
                file_get_contents($srcFile)
            );
            $copied[] = $name;
        }

        return response()->json([
            'status'        => 'ok',
            'published_to'  => $destDir,
            'copied_files'  => $copied,
            'missing_files' => $missing,
        ]);
    }

    public function unpublish(Comparison $comparison)
    {
        try {
            [ $sourceDir, $destDir, $destPath ] = $this->resolvePaths($comparison);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'comparison_id' => $comparison->id,
            ], 422);
        }

        if (!is_dir($destPath)) {
            return response()->json([
                'status' => 'ok',
                'deleted_files' => [],
                'not_found' => self::COMPONENTS,
            ]);
        }

        $deleted = [];
        $notFound = [];
        foreach (self::COMPONENTS as $name) {
            $relative = "{$destDir}/{$name}";
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
                $deleted[] = $name;
            } else {
                $notFound[] = $name;
            }
        }

        if (empty(Storage::disk('public')->files($destDir))
            && empty(Storage::disk('public')->directories($destDir))) {
            Storage::disk('public')->deleteDirectory($destDir);
        }

        return response()->json([
            'status'        => 'ok',
            'deleted_files' => $deleted,
            'not_found'     => $notFound,
        ]);
    }

    private function resolvePaths(Comparison $comparison): array
    {
        $workInfo = DB::table('versions')
            ->where('versions.id', $comparison->source_id)
            ->join('works', 'versions.work_id', '=', 'works.id')
            ->select('works.folder as work_folder', 'works.author_id', 'works.title as work_title')
            ->first();

        if (!$workInfo) {
            throw new \RuntimeException('Impossible de retrouver l’œuvre pour cette comparaison');
        }

        $author = DB::table('authors')
            ->where('id', $workInfo->author_id)
            ->select('folder', 'name')
            ->first();

        if (!$author || !$author->folder) {
            throw new \RuntimeException('Impossible de retrouver le dossier auteur.');
        }

        $basePath  = "uploads/{$author->folder}/{$workInfo->work_folder}";
        $sourceDir = storage_path("app/public/{$basePath}/comparisons/{$comparison->id}");
        if (!is_dir($sourceDir)) {
            $legacy = public_path("{$basePath}/comparisons/{$comparison->id}");
            if (is_dir($legacy)) {
                $sourceDir = $legacy;
            }
        }

        $destDir  = "{$basePath}/{$comparison->folder}";
        $destPath = storage_path("app/public/{$destDir}");

        return [$sourceDir, $destDir, $destPath];
    }
}
