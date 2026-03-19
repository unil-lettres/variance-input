<?php
namespace App\Http\Controllers;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use App\Models\Comparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PublishController;
use App\Models\Version;
use App\Services\PageMarkerService;

class EditorController extends Controller
{
    public function __construct(private PageMarkerService $pageMarkerService)
    {
    }

    private const COMPARISON_COMPONENTS = [
        'source' => ['filename' => 'source.xhtml', 'label' => 'Texte source'],
        'target' => ['filename' => 'target.xhtml', 'label' => 'Texte cible'],
        'd' => ['filename' => 'd.xhtml', 'label' => 'Déplacements'],
        'i' => ['filename' => 'i.xhtml', 'label' => 'Insertions'],
        'r' => ['filename' => 'r.xhtml', 'label' => 'Remplacements'],
        's' => ['filename' => 's.xhtml', 'label' => 'Suppressions'],
    ];

    public function versionEditor(Version $version)
    {
        $defaultReturnTo = admin_path(sprintf(
            'select/%s/%s#etape-2',
            $version->work->author->folder,
            $version->work->folder
        ));
        $returnTo = request()->query('return_to', $defaultReturnTo);

        $manifestEntries = $version->collectManifestEntries();
        $editorPayload = $this->pageMarkerService->buildVersionEditorXml($version, $manifestEntries);
        $xmlContent = $editorPayload['xml'] ?? null;
        if (!is_string($xmlContent) || $xmlContent === '') {
            abort(404, "Fichier introuvable: {$version->getXMLFilePath()}");
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
            }, $manifestEntries),
            'urlFileSave' => admin_url($editorPath),
            'urlToggleIgnored' => admin_url("versions/{$version->id}/facsimiles/toggle-ignored"),
            'returnTo' => $returnTo,
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
        $this->pageMarkerService->syncSidecarWithPb($version);

        return response()->json(['message' => 'Fichier mis à jour avec succès']);
    }

    public function comparisonEditor(Comparison $comparison, Request $request)
    {
        $this->assertComparisonOwnership($comparison);
        $sourcePublicationInfo = $this->getPublicationInfo($comparison, 'source');
        $targetPublicationInfo = $this->getPublicationInfo($comparison, 'target');
        $canEditComparison = !$sourcePublicationInfo['is_published']
            && $sourcePublicationInfo['has_json']
            && $targetPublicationInfo['has_json'];

        $components = collect(self::COMPARISON_COMPONENTS)
            ->map(function (array $meta, string $type) use ($comparison, $canEditComparison) {
                $filePath = $this->resolveComparisonComponentPath($comparison, $type);
                $exists = file_exists($filePath);
                $xmlContent = '';

                if ($exists) {
                    $xmlContent = file_get_contents($filePath);
                    $encoding = $this->detectEncoding($xmlContent);
                    if ($encoding !== 'UTF-8') {
                        $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', $encoding);
                    }
                }

                return [
                    'type' => $type,
                    'label' => $meta['label'],
                    'filename' => $meta['filename'],
                    'exists' => $exists,
                    'xmlContent' => $xmlContent,
                    'urlFileSave' => admin_url('comparison/' . $comparison->id . '/editor?type=' . $type),
                    'urlRemoveTransformation' => admin_url('comparison/' . $comparison->id . '/editor/transformation/remove'),
                    'canEdit' => $canEditComparison,
                ];
            })
            ->values()
            ->all();

        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;

        return view('components.main.editor.comparison', [
            'comparison' => $comparison,
            'work' => $work,
            'components' => $components,
            'isPublished' => $sourcePublicationInfo['is_published'],
            'canEditComparison' => $canEditComparison,
            'missingSourceManifest' => !$sourcePublicationInfo['has_json'],
            'missingTargetManifest' => !$targetPublicationInfo['has_json'],
        ]);
    }
    
    public function comparisonUpdate(Comparison $comparison, Request $request)
    {
        $this->assertComparisonOwnership($comparison);
        $request->validate([
            'type' => 'in:source,target,d,i,r,s'
        ]);

        $type = $request->query('type', 'source');
        $sourcePublicationInfo = $this->getPublicationInfo($comparison, 'source');
        $targetPublicationInfo = $this->getPublicationInfo($comparison, 'target');
        $canEditComparison = !$sourcePublicationInfo['is_published']
            && $sourcePublicationInfo['has_json']
            && $targetPublicationInfo['has_json'];
        
        if (!$canEditComparison) {
            return response()->json(['error' => 'Les modifications ne sont pas autorisées.'], 403);
        }

        $newXml = $request->getContent();
        $path = $this->resolveComparisonComponentPath($comparison, $type);
        
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $originalEncoding = 'UTF-8';
        if (file_exists($path)) {
            $existingContent = file_get_contents($path);
            $originalEncoding = $this->detectEncoding($existingContent);
        }
        $contentToWrite = $originalEncoding === 'UTF-8'
            ? $newXml
            : mb_convert_encoding($newXml, $originalEncoding, 'UTF-8');

        file_put_contents($path, $contentToWrite);

        return response()->json(['message' => 'Fichier mis à jour avec succès']);
    }

    public function removeTransformation(Comparison $comparison, Request $request)
    {
        $this->assertComparisonOwnership($comparison);
        $request->validate([
            'type' => 'required|in:d,i,r,s',
            'ref_id' => ['required', 'string', 'regex:/^[ab][a-z]_[0-9]{5}$/'],
        ]);

        $sourcePublicationInfo = $this->getPublicationInfo($comparison, 'source');
        $targetPublicationInfo = $this->getPublicationInfo($comparison, 'target');
        $canEditComparison = !$sourcePublicationInfo['is_published']
            && $sourcePublicationInfo['has_json']
            && $targetPublicationInfo['has_json'];
        if (!$canEditComparison) {
            return response()->json(['error' => 'Les modifications ne sont pas autorisées.'], 403);
        }

        $type = (string) $request->input('type');
        $refId = (string) $request->input('ref_id');

        $listPath = $this->resolveComparisonComponentPath($comparison, $type);
        if (!file_exists($listPath)) {
            return response()->json(['error' => "Fichier {$type}.xhtml introuvable."], 404);
        }

        $counterpartId = $this->swapRefPrefix($refId);
        $idsToRemove = array_values(array_unique(array_filter([$refId, $counterpartId])));

        $summary = [
            'list_removed' => 0,
            'source_removed' => 0,
            'target_removed' => 0,
            'ids_removed' => $idsToRemove,
        ];

        [$updatedList, $listRemoved] = $this->removeTransformationListItems(file_get_contents($listPath), $refId);
        if ($listRemoved > 0) {
            file_put_contents($listPath, $updatedList);
        }
        $summary['list_removed'] = $listRemoved;

        $sourcePath = $this->resolveComparisonComponentPath($comparison, 'source');
        if (file_exists($sourcePath)) {
            [$updatedSource, $removedSource] = $this->removeElementsByIds(file_get_contents($sourcePath), $idsToRemove);
            if ($removedSource > 0) {
                file_put_contents($sourcePath, $updatedSource);
            }
            $summary['source_removed'] = $removedSource;
        }

        $targetPath = $this->resolveComparisonComponentPath($comparison, 'target');
        if (file_exists($targetPath)) {
            [$updatedTarget, $removedTarget] = $this->removeElementsByIds(file_get_contents($targetPath), $idsToRemove);
            if ($removedTarget > 0) {
                file_put_contents($targetPath, $updatedTarget);
            }
            $summary['target_removed'] = $removedTarget;
        }

        return response()->json([
            'message' => 'Transformation supprimée.',
            'summary' => $summary,
        ]);
    }

    public function comparisonConsistency(Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);

        $components = [];
        foreach (self::COMPARISON_COMPONENTS as $type => $meta) {
            $path = $this->resolveComparisonComponentPath($comparison, $type);
            $components[$type] = [
                'filename' => $meta['filename'],
                'path' => $path,
                'exists' => file_exists($path),
            ];
        }

        $issues = [];
        $sourceTargetIds = [];
        $sourceTargetTransformationIds = [];
        $listRefsByType = [
            'd' => [],
            'i' => [],
            'r' => [],
            's' => [],
        ];

        foreach (['source', 'target'] as $type) {
            $component = $components[$type];
            if (!$component['exists']) {
                $issues[] = [
                    'severity' => 'warning',
                    'file' => $component['filename'],
                    'message' => 'Fichier manquant: impossible de vérifier les références.',
                ];
                continue;
            }

            [$dom, $errors] = $this->parseXmlDocument($component['path']);
            if (!$dom) {
                $issues[] = [
                    'severity' => 'error',
                    'file' => $component['filename'],
                    'message' => 'XHTML invalide (analyse XML/fragment impossible).',
                    'details' => $errors,
                ];
                continue;
            }

            $sourceTargetIds = array_merge($sourceTargetIds, $this->collectDomIds($dom));
        }

        $sourceTargetIds = array_values(array_unique($sourceTargetIds));
        $sourceTargetIdMap = array_flip($sourceTargetIds);
        $sourceTargetTransformationIds = array_values(array_filter(
            $sourceTargetIds,
            fn (string $id) => (bool) preg_match('/^[ab][dirs]_[0-9]{5}$/', $id)
        ));

        foreach (['d', 'i', 'r', 's'] as $type) {
            $component = $components[$type];
            if (!$component['exists']) {
                continue;
            }

            [$dom, $errors] = $this->parseXmlDocument($component['path']);
            if (!$dom) {
                $issues[] = [
                    'severity' => 'error',
                    'file' => $component['filename'],
                    'message' => 'XHTML invalide (analyse XML/fragment impossible).',
                    'details' => $errors,
                ];
                continue;
            }

            $liCount = $dom->getElementsByTagName('li')->length;
            $refIds = $this->collectAnchorFragmentRefs($dom);
            $listRefsByType[$type] = array_values(array_unique($refIds));
            $missing = array_values(array_filter($refIds, fn (string $id) => !isset($sourceTargetIdMap[$id])));

            if (!empty($missing)) {
                $sample = array_slice(array_values(array_unique($missing)), 0, 10);
                $issues[] = [
                    'severity' => 'warning',
                    'file' => $component['filename'],
                    'message' => count($missing) . ' référence(s) introuvable(s) dans source/target.',
                    'details' => ['missing_refs_sample' => $sample],
                ];
            }

            if ($liCount > 0 && count($refIds) === 0) {
                $issues[] = [
                    'severity' => 'warning',
                    'file' => $component['filename'],
                    'message' => "Liste de transformations sans ancres de référence (#id).",
                ];
            }
        }

        // Reverse consistency check:
        // if transformation ids remain in source/target but are no longer referenced in d/i/r/s lists.
        $referencedTransformationIds = [];
        foreach (['d', 'i', 'r', 's'] as $type) {
            foreach ($listRefsByType[$type] as $refId) {
                $referencedTransformationIds[$refId] = true;
                $counterpart = $this->swapRefPrefix($refId);
                if ($counterpart) {
                    $referencedTransformationIds[$counterpart] = true;
                }
            }
        }

        $orphanTransformationIds = array_values(array_filter(
            $sourceTargetTransformationIds,
            fn (string $id) => !isset($referencedTransformationIds[$id])
        ));
        if (!empty($orphanTransformationIds)) {
            $issues[] = [
                'severity' => 'warning',
                'file' => 'source.xhtml / target.xhtml',
                'message' => count($orphanTransformationIds) . ' transformation(s) orpheline(s) présentes dans source/target sans entrée d/i/r/s.',
                'details' => [
                    'orphan_ids_sample' => array_slice($orphanTransformationIds, 0, 20),
                ],
            ];
        }

        $status = 'ok';
        if (collect($issues)->contains(fn (array $i) => $i['severity'] === 'error')) {
            $status = 'error';
        } elseif (!empty($issues)) {
            $status = 'warning';
        }

        return response()->json([
            'status' => $status,
            'issues' => $issues,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function resolveComparisonComponentPath(Comparison $comparison, string $type): string
    {
        if (!array_key_exists($type, self::COMPARISON_COMPONENTS)) {
            abort(422, "Type de composant invalide: {$type}");
        }

        if ($type === 'source') {
            return $comparison->getSourceFilePath();
        }

        if ($type === 'target') {
            return $comparison->getTargetFilePath();
        }

        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        $authorFolder = $work?->author?->folder;
        $workFolder = $work?->folder;
        if (!$authorFolder || !$workFolder) {
            abort(422, "Impossible de déterminer le dossier pour cette comparaison.");
        }

        return storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}/{$type}.xhtml");
    }

    private function swapRefPrefix(string $refId): ?string
    {
        if (strlen($refId) < 2) {
            return null;
        }
        $first = $refId[0];
        if ($first === 'a') {
            return 'b' . substr($refId, 1);
        }
        if ($first === 'b') {
            return 'a' . substr($refId, 1);
        }

        return null;
    }

    private function removeTransformationListItems(string $content, string $refId): array
    {
        $escapedRef = preg_quote($refId, '~');
        $patterns = [
            '~<li\b[^>]*>\s*<a\b[^>]*href="#' . $escapedRef . '"[^>]*>.*?</a>\s*</li>\s*~si',
            "~<li\b[^>]*>\s*<a\b[^>]*href='#" . $escapedRef . "'[^>]*>.*?</a>\s*</li>\s*~si",
        ];

        $total = 0;
        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content, -1, $count);
            $total += $count;
        }

        return [$content, $total];
    }

    private function removeElementsByIds(string $content, array $ids): array
    {
        $total = 0;
        foreach ($ids as $id) {
            $escapedId = preg_quote($id, '~');
            $patterns = [
                '~<([a-zA-Z][\w:\-]*)\b[^>]*\bid="' . $escapedId . '"[^>]*>.*?</\1>\s*~si',
                "~<([a-zA-Z][\\w:\\-]*)\\b[^>]*\\bid='" . $escapedId . "'[^>]*>.*?</\\1>\\s*~si",
                '~<([a-zA-Z][\w:\-]*)\b[^>]*\bid="' . $escapedId . '"[^>]*/>\s*~si',
                "~<([a-zA-Z][\\w:\\-]*)\\b[^>]*\\bid='" . $escapedId . "'[^>]*/>\\s*~si",
            ];
            foreach ($patterns as $pattern) {
                $content = preg_replace($pattern, '', $content, -1, $count);
                $total += $count;
            }
        }

        return [$content, $total];
    }

    private function parseXmlDocument(string $path): array
    {
        $content = file_get_contents($path);
        $encoding = $this->detectEncoding($content);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $content);
            if ($sanitized !== false) {
                $content = $sanitized;
            }
        }

        [$dom, $errors] = $this->loadXmlAttempt($content);
        if ($dom) {
            return [$dom, []];
        }

        // Medite outputs are often XHTML fragments (multiple top-level nodes).
        $wrapped = '<root>' . $content . '</root>';
        [$fragmentDom, $fragmentErrors] = $this->loadXmlAttempt($wrapped);
        if ($fragmentDom) {
            return [$fragmentDom, []];
        }

        return [null, [
            'direct_xml' => $errors,
            'wrapped_fragment' => $fragmentErrors,
        ]];
    }

    private function loadXmlAttempt(string $xml): array
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        $errors = $loaded ? [] : $this->formatLibxmlErrors();

        libxml_clear_errors();

        return [$loaded ? $dom : null, $errors];
    }

    private function formatLibxmlErrors(): array
    {
        $out = [];
        foreach (libxml_get_errors() as $error) {
            $message = trim($error->message);
            $line = (int) ($error->line ?? 0);
            $column = (int) ($error->column ?? 0);
            $out[] = sprintf('L%d:C%d %s', max($line, 0), max($column, 0), $message);
        }

        return array_slice($out, 0, 8);
    }

    private function collectDomIds(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@id]');
        $ids = [];
        foreach ($nodes as $node) {
            $id = trim((string) $node->attributes?->getNamedItem('id')?->nodeValue);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function collectAnchorFragmentRefs(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a[starts-with(@href, "#")]');
        $refs = [];

        foreach ($nodes as $node) {
            $href = (string) $node->attributes?->getNamedItem('href')?->nodeValue;
            if ($href === '') {
                continue;
            }
            $fragment = ltrim($href, '#');
            if ($fragment === '') {
                continue;
            }
            $fragment = rawurldecode($fragment);
            $refs[] = $fragment;
        }

        return $refs;
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
            'has_json' => $imagesData !== null,
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
