<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PublishController;
use App\Models\Version;

class EditorController extends Controller
{
    public function versionEditor(Version $version)
    {
        $path = $version->getXMLFilePath();
        if (!file_exists($path)) {
            abort(404, "Fichier introuvable: {$path}");
        }
    
        $xmlContent = file_get_contents($path);
        $encoding = $this->detectEncoding($xmlContent);
        if ($encoding !== 'UTF-8') {
            $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', $encoding);
        }
        $editorPath = 'version/' . $version->id . '/editor';
        $ignoredPages = $version->getIgnoredPages();

        return view('components.main.editor.version', [
            'version' => $version,
            'xmlContent' => $xmlContent,
            'imagesData' => array_map(function ($item) use ($ignoredPages) {
                $filename = basename($item['big']);
                return [
                    'small' => $item['small'],
                    'big' => $item['big'],
                    'filename' => $filename,
                    'ignored' => $ignoredPages->contains($filename),
                ];
            }, $version->collectManifestEntries()),
            'urlFileSave' => admin_url($editorPath),
            'urlToggleIgnored' => admin_url("versions/{$version->id}/facsimiles/toggle-ignored"),
        ]);
    }

    public function versionUpdate(Version $version, Request $request)
    {
        $newXml = $request->getContent();
        $path = $version->getXMLFilePath();
        
        if (!file_exists($path)) {
            abort(404, "Fichier introuvable: {$path}");
        }

        $existingContent = file_get_contents($path);
        $originalEncoding = $this->detectEncoding($existingContent);
        $contentToWrite = $originalEncoding === 'UTF-8'
            ? $newXml
            : mb_convert_encoding($newXml, $originalEncoding, 'UTF-8');

        file_put_contents($path, $contentToWrite);

        return response()->json(['message' => 'Fichier mis à jour avec succès']);
    }

    public function comparisonEditor(Comparison $comparison, Request $request)
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
            abort(404, "Fichier introuvable: {$path}");
        }
    
        $xmlContent = file_get_contents($path);
        $encoding = $this->detectEncoding($xmlContent);
        if ($encoding !== 'UTF-8') {
            $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', $encoding);
        }
        $editorPath = 'comparison/' . $comparison->id . '/editor';

        $publicationInfo = $this->getPublicationInfo($comparison, $type);

        return view('components.main.editor.comparison', [
            'comparison' => $comparison,
            'version' => $version,
            'xmlContent' => $xmlContent,
            'isSource' => !$isTarget,
            'isPublished' => $publicationInfo['is_published'],
            'canEdit' => $publicationInfo['can_edit'],
            'imagesData' => $publicationInfo['images_data'],
            'sourceTabUrl' => admin_url($editorPath . '?type=source'),
            'targetTabUrl' => admin_url($editorPath . '?type=target'),
            'urlFileSave' => admin_url($editorPath . '?' . http_build_query(['type' => $type])),
        ]);
    }
    
    public function comparisonUpdate(Comparison $comparison, Request $request)
    {
        $request->validate([
            'type' => 'in:source,target'
        ]);

        $type = $request->query('type', 'source');
        $publicationInfo = $this->getPublicationInfo($comparison, $type);
        
        if (!$publicationInfo['can_edit']) {
            return response()->json(['error' => 'Les modifications ne sont pas autorisées.'], 403);
        }

        $newXml = $request->getContent();
        $path = match ($request->query('type', 'source')) {
            'source' => $comparison->getSourceFilePath(),
            'target' => $comparison->getTargetFilePath(),
        };
        
        if (!file_exists($path)) {
            abort(404, "Fichier introuvable: {$path}");
        }

        $existingContent = file_get_contents($path);
        $originalEncoding = $this->detectEncoding($existingContent);
        $contentToWrite = $originalEncoding === 'UTF-8'
            ? $newXml
            : mb_convert_encoding($newXml, $originalEncoding, 'UTF-8');

        file_put_contents($path, $contentToWrite);

        return response()->json(['message' => 'Fichier mis à jour avec succès']);
    }

    /**
     * Get publication information including the status and images JSON data.
     * Returns [
     *   'is_published' => bool,
     *   'has_json' => bool,
     *   'images_data' => array|null,
     *   'can_edit' => bool
     * ]
     */
    private function getPublicationInfo(Comparison $comparison, string $type = 'source'): array
    {
        $workInfo = DB::table('versions')
            ->where('versions.id', $comparison->source_id)
            ->join('works', 'versions.work_id', '=', 'works.id')
            ->select('works.folder as work_folder', 'works.author_id')
            ->first();

        if (!$workInfo) {
            return [
                'is_published' => false,
                'has_json' => false,
                'images_data' => null,
                'can_edit' => false
            ];
        }

        $authorFolder = DB::table('authors')
            ->where('id', $workInfo->author_id)
            ->value('folder');

        if (!$authorFolder) {
            return [
                'is_published' => false,
                'has_json' => false,
                'images_data' => null,
                'can_edit' => false
            ];
        }

        $destDir = "uploads/{$authorFolder}/{$workInfo->work_folder}/{$comparison->folder}";
        $required = PublishController::COMPONENTS;

        // Check if all required files exist (is published)
        $isPublished = true;
        foreach ($required as $file) {
            if (!Storage::disk('public')->exists("{$destDir}/{$file}")) {
                $isPublished = false;
                break;
            }
        }

        // Check for images JSON file
        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workInfo->work_folder, $comparison->folder));
        
        $versionId = ($type === 'target') ? $comparison->target_id : $comparison->source_id;
        $version = DB::table('versions')->select('folder')->find($versionId);
        
        $imagesData = null;

        if ($version && $version->folder) {
            $jsonPath = "uploads/{$authorFolder}/{$workInfo->work_folder}/{$version->folder}/images_{$type}_{$baseName}.json";
            if (Storage::disk('public')->exists($jsonPath)) {
                $jsonContent = Storage::disk('public')->get($jsonPath);
                $imagesData = $this->parseImagesData(json_decode($jsonContent, true) ?? []);
            }
        }

        return [
            'is_published' => $isPublished,
            'images_data' => $imagesData,
            'can_edit' => !$isPublished && $imagesData !== null,
        ];
    }

    private function parseImagesData(array $imagesData): array
    {
        return array_map(function ($item) {
            return [
                'small' => admin_url(Storage::url(ltrim($item['small'], '/'))),
                'big'   => admin_url(Storage::url(ltrim($item['big'], '/'))),
                'filename' => basename($item['big']),
            ];
        }, $imagesData);
    }

    private function detectEncoding(string $content): string
    {
        $detected = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);

        return $detected ?: 'UTF-8';
    }
}
