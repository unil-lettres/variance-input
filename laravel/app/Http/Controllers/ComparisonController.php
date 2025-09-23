<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Comparison;
use App\Http\Controllers\PublishController;

class ComparisonController extends Controller
{
    /**
     * Return all comparisons connected to a work (by its versions).
     */
    public function getByWork(Request $request)
    {
        $request->validate(['work_id' => 'required|exists:works,id']);

        $workId = (int) $request->input('work_id');

        $versionIds = DB::table('versions')
            ->where('work_id', $workId)
            ->pluck('id');

        $workInfo = DB::table('works')
            ->where('id', $workId)
            ->first(['folder', 'author_id']);

        $authorFolder = $workInfo
            ? DB::table('authors')->where('id', $workInfo->author_id)->value('folder')
            : null;

        $destBase = ($authorFolder && $workInfo)
            ? "uploads/{$authorFolder}/{$workInfo->folder}"
            : null;

        $required = PublishController::COMPONENTS;

        $comparisons = Comparison::with(['sourceVersion', 'targetVersion'])
            ->whereIn('source_id', $versionIds)
            ->orWhereIn('target_id', $versionIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Comparison $cmp) use ($destBase, $required) {
                $cmp->published = false;
                $cmp->publish_missing = $required;
                $cmp->publish_dest = null;

                if (!$destBase) {
                    return $cmp;
                }

                $destDir   = "{$destBase}/{$cmp->folder}";
                $fullDir   = storage_path("app/public/{$destDir}");
                $sourceDir = storage_path("app/public/{$destBase}/comparisons/{$cmp->id}");
                if (!is_dir($sourceDir)) {
                    $legacy = public_path("{$destBase}/comparisons/{$cmp->id}");
                    if (is_dir($legacy)) {
                        $sourceDir = $legacy;
                    }
                }

                $missing = [];
                if (is_dir($sourceDir)) {
                    foreach ($required as $file) {
                        if (!is_file($sourceDir . DIRECTORY_SEPARATOR . $file)) {
                            $missing[] = $file;
                        }
                    }
                } else {
                    $missing = $required;
                }

                $alreadyPublished = 0;
                foreach ($required as $file) {
                    if (is_file($fullDir . DIRECTORY_SEPARATOR . $file)) {
                        $alreadyPublished++;
                    }
                }

                $cmp->published = ($alreadyPublished === count($required));
                $cmp->publish_missing = $missing;
                $cmp->publish_dest = $destDir;
                $cmp->publish_source = $sourceDir;
                $cmp->components_ready = empty($missing);

                Log::debug('Comparison components status', [
                    'comparison_id' => $cmp->id,
                    'source_dir'    => $sourceDir,
                    'missing'       => $missing,
                ]);

                return $cmp;
            });

        return response()->json($comparisons);
    }

    /**
     * Delete comparison DB row **and** its generated HTML / XML files.
     * Files are stored in `uploads/comparisons/{comparison_id}.xml|html`.
     */
    public function destroy(int $id)
    {
        $comparison = Comparison::findOrFail($id);

        $destInfo = $this->resolvePublicationPaths($comparison);

        if ($destInfo['published']) {
            return response()->json([
                'error' => 'Cette comparaison est actuellement publiée. Dépubliez-la avant de la supprimer.'
            ], 409);
        }

        $relativeFolder = 'uploads/comparisons';
        $filename       = $comparison->id; // use unique id, not short name

        $htmlPath = "{$relativeFolder}/{$filename}.html";
        $xmlPath  = "{$relativeFolder}/{$filename}.xml";

        // Remove files if present
        Storage::disk('public')->delete([$htmlPath, $xmlPath]);

        if (is_dir($destInfo['source_dir'])) {
            $storageRoot = storage_path('app/public/');
            if (str_starts_with($destInfo['source_dir'], $storageRoot)) {
                $relative = substr($destInfo['source_dir'], strlen($storageRoot));
                Storage::disk('public')->deleteDirectory($relative);
            } else {
                File::deleteDirectory($destInfo['source_dir']);
            }
        }

        // Remove DB record
        $comparison->delete();

        return response()->json(['message' => 'Comparison deleted']);
    }

    private function resolvePublicationPaths(Comparison $comparison): array
    {
        $workInfo = DB::table('versions')
            ->where('versions.id', $comparison->source_id)
            ->join('works', 'versions.work_id', '=', 'works.id')
            ->select('works.folder as work_folder', 'works.author_id')
            ->first();

        if (!$workInfo) {
            return ['source_dir' => storage_path("app/public/uploads/comparisons/{$comparison->id}"), 'published' => false];
        }

        $authorFolder = DB::table('authors')
            ->where('id', $workInfo->author_id)
            ->value('folder');

        if (!$authorFolder) {
            return ['source_dir' => storage_path("app/public/uploads/comparisons/{$comparison->id}"), 'published' => false];
        }

        $basePath  = "uploads/{$authorFolder}/{$workInfo->work_folder}";
        $sourceDir = storage_path("app/public/{$basePath}/comparisons/{$comparison->id}");
        $destDir   = "uploads/{$authorFolder}/{$workInfo->work_folder}/{$comparison->folder}";

        $published = true;
        foreach (PublishController::COMPONENTS as $file) {
            if (!Storage::disk('public')->exists("{$destDir}/{$file}")) {
                $published = false;
                break;
            }
        }

        return [
            'source_dir' => $sourceDir,
            'dest_dir'   => $destDir,
            'published'  => $published,
        ];
    }
}
