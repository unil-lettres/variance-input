<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PublishController;

class EditorController extends Controller
{
    public function show(Comparison $comparison, Request $request)
    {
        $request->validate([
            'type' => 'in:source,target'
        ]);

        $type = $request->query('type', 'source');
        $isTarget = $type === 'target';

        if ($isTarget) {
          $version = $comparison->targetVersion;
          $path = $comparison->getTargetFilePath();
        } else {
          $version = $comparison->sourceVersion;
          $path = $comparison->getSourceFilePath();
        }

        if (!file_exists($path)) {
            abort(404, "XML file not found at: {$path}");
        }
    
        $xmlContent = file_get_contents($path);

        $isPublished = $this->isComparisonPublished($comparison);
    
        return view('components.main.editor', [
            'comparison' => $comparison,
            'version' => $version,
            'xmlContent' => $xmlContent,
            'isSource' => !$isTarget,
            'isPublished' => $isPublished,
        ]);
    }
    

    public function update(Comparison $comparison, Request $request)
    {
        $request->validate([
            'type' => 'in:source,target'
        ]);

        if ($this->isComparisonPublished($comparison)) {
            return response()->json([
                'error' => 'Cette comparaison est actuellement publiée. Les modifications ne sont pas autorisées.'
            ], 403);
        }

        $newXml = $request->getContent();
        $path = match ($request->query('type', 'source')) {
            'source' => $comparison->getSourceFilePath(),
            'target' => $comparison->getTargetFilePath(),
        };
        
        if (!file_exists($path)) {
            abort(404, "File not found: {$path}");
        }

        file_put_contents($path, $newXml);

        return response()->json(['message' => 'XML updated successfully']);
    }

    /**
    * Check if a comparison is published by verifying that all required files
    * exist in the destination directory.
     */
    private function isComparisonPublished(Comparison $comparison): bool
    {
        $workInfo = DB::table('versions')
            ->where('versions.id', $comparison->source_id)
            ->join('works', 'versions.work_id', '=', 'works.id')
            ->select('works.folder as work_folder', 'works.author_id')
            ->first();

        if (!$workInfo) {
            return false;
        }

        $authorFolder = DB::table('authors')
            ->where('id', $workInfo->author_id)
            ->value('folder');

        if (!$authorFolder) {
            return false;
        }

        $destDir = "uploads/{$authorFolder}/{$workInfo->work_folder}/{$comparison->folder}";
        $required = PublishController::COMPONENTS;

        foreach ($required as $file) {
            if (!Storage::disk('public')->exists("{$destDir}/{$file}")) {
                return false;
            }
        }

        return true;
    }
}
