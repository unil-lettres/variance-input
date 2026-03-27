<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Models\Comparison;
use App\Models\Version;
use App\Models\User;
use App\Http\Controllers\PublishController;
use App\Services\PageMarkerService;
use App\Jobs\InjectComparisonPaginationJob;
use App\Jobs\GenerateLegacyExportJob;
use App\Services\LegacyExportService;

class ComparisonController extends Controller
{
    public function __construct(
        private PageMarkerService $pageMarkerService,
        private LegacyExportService $legacyExportService
    )
    {
    }

    /**
     * Return all comparisons connected to a work (by its versions).
     */
    public function getByWork(Request $request)
    {
        $request->validate(['work_id' => 'required|exists:works,id']);

        $workId = (int) $request->input('work_id');
        $light = $request->boolean('light', false);

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
        $workFolder = $workInfo->folder ?? null;

        $required = PublishController::COMPONENTS;

        $user = $request->user();

        $comparisonsQuery = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])
            ->where(function ($query) use ($versionIds) {
                $query->whereIn('source_id', $versionIds)
                    ->orWhereIn('target_id', $versionIds);
            });

        $this->scopeComparisonsToUser($comparisonsQuery, $user);

        $comparisons = $comparisonsQuery
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Comparison $cmp) use ($destBase, $required, $authorFolder, $workFolder, $light) {
                if ($light) {
                    $publicationScope = $cmp->publication_scope ?? ($cmp->is_legacy ? 'prod' : null);
                    $cmp->publication_scope = $publicationScope;
                    $cmp->published = $publicationScope !== null;
                    $cmp->publish_missing = null;
                    $cmp->publish_dest = null;
                    $cmp->publish_source = null;
                    $cmp->components_ready = null;
                    $cmp->xml_available = null;
                    $cmp->medite_component_counts = null;
                    $cmp->pagination = null;
                    $cmp->manifests  = null;
                    $cmp->comparison_progress = null;
                    $cmp->export_bundle = $this->exportBundlePayload($cmp);
                    $cmp->details_loaded = false;
                    return $cmp;
                }

                return $this->enrichComparison($cmp, $destBase, $authorFolder, $workFolder, $required);
            });

        return response()->json($comparisons);
    }

    public function details(Comparison $comparison)
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $this->assertComparisonOwnership($comparison);

        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        $authorFolder = $work?->author?->folder;
        $workFolder   = $work?->folder;
        $destBase = ($authorFolder && $workFolder) ? "uploads/{$authorFolder}/{$workFolder}" : null;

        $required = PublishController::COMPONENTS;

        $comparison = $this->enrichComparison($comparison, $destBase, $authorFolder, $workFolder, $required);
        $comparison->details_loaded = true;

        return response()->json($comparison);
    }

    private function enrichComparison(
        Comparison $cmp,
        ?string $destBase,
        ?string $authorFolder,
        ?string $workFolder,
        array $required
    ): Comparison {
        // Do not inject default pagination markers anymore; rely on real facsimiles/_lignes only

        $cmp->published = false;
        $cmp->publish_missing = $required;
        $cmp->publish_dest = null;
        $publicationScope = $cmp->publication_scope ?? null;

        if (!$destBase) {
            if (!$publicationScope && $cmp->is_legacy) {
                $publicationScope = 'prod';
            }
            $cmp->publication_scope = $publicationScope;
            $cmp->published = $publicationScope !== null;
            $cmp->details_loaded = true;
            return $cmp;
        }

        $destDir   = "{$destBase}/{$cmp->folder}";
        $fullDir   = storage_path("app/public/{$destDir}");
        $publicDir = public_path($destDir);
        $sourceDir = storage_path("app/public/{$destBase}/comparisons/{$cmp->id}");
        if (!is_dir($sourceDir)) {
            $legacy = public_path("{$destBase}/comparisons/{$cmp->id}");
            if (is_dir($legacy)) {
                $sourceDir = $legacy;
            }
        }

        $legacyDir = null;
        if ($authorFolder && $workFolder && $cmp->folder) {
            $candidate = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$cmp->folder}");
            if (is_dir($candidate)) {
                $legacyDir = $candidate;
            }
        }

        $publishedDir = is_dir($fullDir) ? $fullDir : (is_dir($publicDir) ? $publicDir : null);
        if ($cmp->is_legacy && $legacyDir) {
            $publishedDir = $legacyDir;
        }

        $componentDir = is_dir($sourceDir) ? $sourceDir : $publishedDir;
        if ($cmp->is_legacy && $legacyDir) {
            $componentDir = $legacyDir;
            $sourceDir = $legacyDir;
        }

        $missing = [];
        if ($componentDir && is_dir($componentDir)) {
            foreach ($required as $file) {
                if (!is_file($componentDir . DIRECTORY_SEPARATOR . $file)) {
                    $missing[] = $file;
                }
            }
        } else {
            $missing = $required;
        }

        $alreadyPublished = 0;
        if ($publishedDir) {
            foreach ($required as $file) {
                if (is_file($publishedDir . DIRECTORY_SEPARATOR . $file)) {
                    $alreadyPublished++;
                }
            }
        }

        if (!$publicationScope && $cmp->is_legacy) {
            $publicationScope = 'prod';
        }
        if (!$publicationScope && $alreadyPublished === count($required)) {
            $publicationScope = 'prod';
        }

        $cmp->publication_scope = $publicationScope;
        $cmp->published = $publicationScope !== null;
        $cmp->publish_missing = $missing;
        $cmp->publish_dest = $destDir;
        $cmp->publish_source = is_dir($sourceDir) ? $sourceDir : null;
        $cmp->components_ready = empty($missing);
        if ($cmp->is_legacy) {
            $cmp->publish_source = null;
        }
        $cmp->xml_available = is_file(storage_path("app/public/uploads/comparisons/{$cmp->id}.xml"));

        $cmp->medite_component_counts = $this->countMediteComponentEntries($componentDir);

        Log::debug('Comparison components status', [
            'comparison_id' => $cmp->id,
            'source_dir'    => $sourceDir,
            'missing'       => $missing,
        ]);

        $cmp->pagination = $this->pageMarkerService->countMarkersForComparison($cmp);
        $cmp->manifests  = $this->manifestStatusForComparison($cmp, $authorFolder, $workFolder);
        $minUpdatedAt = $cmp->created_at ? $cmp->created_at->getTimestamp() : null;
        $cmp->comparison_progress = $this->pageMarkerService->getComparisonProgressSnapshot($cmp->id, $minUpdatedAt);
        $cmp->export_bundle = $this->exportBundlePayload($cmp);
        $cmp->details_loaded = true;

        return $cmp;
    }

    private function countMediteComponentEntries(?string $componentDir): array
    {
        if (!$componentDir || !is_dir($componentDir)) {
            return [];
        }

        $files = [
            'd' => 'd.xhtml',
            'i' => 'i.xhtml',
            'r' => 'r.xhtml',
            's' => 's.xhtml',
        ];

        $counts = [];
        foreach ($files as $key => $filename) {
            $path = $componentDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                continue;
            }
            $counts[$key] = $this->countFileOccurrences($path, '<li');
        }

        return $counts;
    }

    private function countFileOccurrences(string $path, string $needle): int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        $carry = '';
        $needleLen = strlen($needle);
        $carryLen = max($needleLen - 1, 0);

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false) {
                    break;
                }
                $buffer = $carry . $chunk;
                $count += substr_count($buffer, $needle);
                $carry = $carryLen > 0 ? substr($buffer, -$carryLen) : '';
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * Return comparison publication counts for prod/dev.
     */
    public function publicationCounts()
    {
        $prodCount = Comparison::where('publication_scope', 'prod')->count();
        $devCount = Comparison::where('publication_scope', 'dev')->count();
        $legacyProd = Comparison::whereNull('publication_scope')
            ->where('is_legacy', true)
            ->count();

        return response()->json([
            'prod' => $prodCount + $legacyProd,
            'dev'  => $devCount,
        ]);
    }

    public function publicMenu()
    {
        $rows = DB::table('comparisons as c')
            ->leftJoin('versions as vs', 'vs.id', '=', 'c.source_id')
            ->leftJoin('versions as vt', 'vt.id', '=', 'c.target_id')
            ->join('works as w', function ($join) {
                $join->on('w.id', '=', 'vs.work_id')
                    ->orOn('w.id', '=', 'vt.work_id');
            })
            ->join('authors as a', 'a.id', '=', 'w.author_id')
            ->where(function ($query) {
                $query->whereIn('c.publication_scope', ['prod', 'dev'])
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('c.publication_scope')
                            ->where('c.is_legacy', true);
                    });
            })
            ->orderByRaw('LOWER(SUBSTRING_INDEX(TRIM(a.name), \' \', -1)) asc')
            ->orderByRaw('LOWER(a.name) asc')
            ->orderByRaw("
                LOWER(
                    TRIM(
                        REGEXP_REPLACE(
                            TRIM(w.title),
                            '^(d''|de l''|de la |des |du |de |aux |au |l''|le |la |les |un |une )',
                            ''
                        )
                    )
                ) asc
            ")
            ->orderByRaw('LOWER(w.title) asc')
            ->orderByRaw('LOWER(COALESCE(vs.name, \'\')) asc')
            ->orderByRaw('LOWER(COALESCE(vt.name, \'\')) asc')
            ->get([
                'c.id',
                'c.folder as comparison_folder',
                'c.publication_scope',
                'c.is_legacy',
                'a.name as author_name',
                'a.folder as author_folder',
                'w.title as work_title',
                'w.folder as work_folder',
                'vs.name as source_version_name',
                'vt.name as target_version_name',
            ]);

        $tree = [
            'prod' => [],
            'dev' => [],
        ];

        foreach ($rows as $row) {
            $scope = $row->publication_scope ?: ($row->is_legacy ? 'prod' : null);
            if (!in_array($scope, ['prod', 'dev'], true)) {
                continue;
            }

            $pairKey = $row->author_folder . '/' . $row->work_folder;

            if (!isset($tree[$scope][$pairKey])) {
                $tree[$scope][$pairKey] = [
                    'key' => $pairKey,
                    'author_label' => $row->author_name,
                    'work_label' => $row->work_title,
                    'pair_label' => $row->author_name . ' - ' . $row->work_title,
                    'comparisons' => [],
                ];
            }

            $sourceVersionName = trim((string) ($row->source_version_name ?? ''));
            $targetVersionName = trim((string) ($row->target_version_name ?? ''));
            $comparisonLabel = collect([$sourceVersionName, $targetVersionName])
                ->filter()
                ->join(' - ');
            if ($comparisonLabel === '') {
                $comparisonLabel = $row->comparison_folder ?: ('#' . $row->id);
            }
            $comparisonPath = $scope === 'prod'
                ? "{$row->author_folder}/{$row->work_folder}/comparaison/{$row->comparison_folder}"
                : "dev/{$row->author_folder}/{$row->work_folder}/comparaison/{$row->id}";

            $tree[$scope][$pairKey]['comparisons'][] = [
                'id' => (int) $row->id,
                'label' => $comparisonLabel,
                'url' => legacy_url($comparisonPath),
            ];
        }

        return response()->json([
            'prod' => array_values($tree['prod']),
            'dev' => array_values($tree['dev']),
        ]);
    }

    /**
     * Delete comparison DB row **and** its generated HTML / XML files.
     * Files are stored in `uploads/comparisons/{comparison_id}.xml|html`.
     */
    public function destroy(int $id)
    {
        $comparison = Comparison::with([
            'sourceVersion.work.author',
            'targetVersion.work.author',
        ])->findOrFail($id);
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);

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

        $authorFolder = $comparison->sourceVersion?->work?->author?->folder
            ?? $comparison->targetVersion?->work?->author?->folder;
        $workFolder = $comparison->sourceVersion?->work?->folder
            ?? $comparison->targetVersion?->work?->folder;

        if ($authorFolder && $workFolder) {
            $comparisonRelative = "uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}";
            Storage::disk('public')->deleteDirectory($comparisonRelative);
            $legacyComparisonDir = base_path('../variance/' . $comparisonRelative);
            if (is_dir($legacyComparisonDir)) {
                File::deleteDirectory($legacyComparisonDir);
            }

            $publishedRelative = "uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}";
            Storage::disk('public')->deleteDirectory($publishedRelative);
            $legacyPublishedDir = base_path('../variance/' . $publishedRelative);
            if (is_dir($legacyPublishedDir)) {
                File::deleteDirectory($legacyPublishedDir);
            }
        }

        // Remove stale comparison progress snapshot and DB record
        $this->pageMarkerService->clearComparisonProgress($comparison->id);
        $this->legacyExportService->deleteExportArtifacts($comparison->id);
        $comparison->delete();

        return response()->json(['message' => 'Comparison deleted']);
    }

    public function exportPublishedLegacy(Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);

        $snapshot = $this->legacyExportService->getSnapshot($comparison->id);
        $absolutePath = $this->legacyExportService->absolutePathFromSnapshot($snapshot);

        if (($snapshot['status'] ?? null) !== 'ready' || !$absolutePath || !is_file($absolutePath)) {
            abort(404, 'Aucune archive prête. Lancez d’abord la préparation de l’export.');
        }

        $downloadName = $snapshot['file_name'] ?: (($comparison->folder ?: 'comparison_' . $comparison->id) . '_legacy.zip');
        return response()->download($absolutePath, $downloadName);
    }

    public function queueLegacyExport(Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $snapshot = $this->legacyExportService->getSnapshot($comparison->id);
        if (in_array($snapshot['status'] ?? 'idle', ['queued', 'running', 'ready'], true)) {
            return response()->json($this->exportBundlePayload($comparison), ($snapshot['status'] ?? null) === 'ready' ? 200 : 202);
        }

        $this->legacyExportService->markQueued($comparison);
        GenerateLegacyExportJob::dispatch($comparison->id)->onQueue('exports');

        return response()->json($this->exportBundlePayload($comparison), 202);
    }

    public function exportStatus(Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        return response()->json($this->exportBundlePayload($comparison));
    }

    public function downloadLegacyExport(Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);

        $snapshot = $this->legacyExportService->getSnapshot($comparison->id);
        $absolutePath = $this->legacyExportService->absolutePathFromSnapshot($snapshot);
        if (($snapshot['status'] ?? null) !== 'ready' || !$absolutePath || !is_file($absolutePath)) {
            abort(404, 'Aucune archive prête au téléchargement.');
        }

        $downloadName = $snapshot['file_name'] ?: (($comparison->folder ?: 'comparison_' . $comparison->id) . '_legacy.zip');
        return response()->download($absolutePath, $downloadName);
    }

    public function applyPageMarkers(Request $request, Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);
        $this->assertComparisonUnpublished($comparison);
        $validated = $request->validate([
            'clear_existing'   => 'sometimes|boolean',
            'replace_existing' => 'sometimes|boolean',
        ]);

        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $clear   = Arr::get($validated, 'clear_existing', true);
        $replace = Arr::get($validated, 'replace_existing', true);
        $roleParam = strtolower((string) $request->input('role', ''));
        $roleParam = $roleParam !== '' ? $roleParam : null;

        if ($roleParam && !in_array($roleParam, ['source', 'target'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Rôle invalide. Utilisez "source" ou "target".',
            ], 422);
        }

        $roles = [
            'source' => $comparison->sourceVersion,
            'target' => $comparison->targetVersion,
        ];

        $selectedRoles = $roleParam ? [$roleParam => ($roles[$roleParam] ?? null)] : $roles;

        $missing = [];
        $queuedRoles = [];
        foreach ($selectedRoles as $role => $version) {
            if (!$version) {
                $missing[] = "Version {$role} manquante";
                continue;
            }
            $sidecar = $this->pageMarkerService->loadPaginationSidecar($version->id);
            if (!$sidecar) {
                $missing[] = "Sidecar pagination manquant pour la version {$version->name}";
                continue;
            }
            $queuedRoles[$role] = [
                'version_id' => $version->id,
                'total'      => is_array($sidecar['markers'] ?? null)
                    ? count($sidecar['markers'])
                    : (int) ($sidecar['marker_count'] ?? 0),
            ];
        }

        if (!empty($missing)) {
            return response()->json([
                'status'  => 'error',
                'message' => implode(' | ', $missing),
            ], 422);
        }

        $this->pageMarkerService->markComparisonQueued($comparison->id, $queuedRoles);

        $this->flushPendingPaginationJobs($comparison->id, $roleParam);

        InjectComparisonPaginationJob::dispatch(
            comparisonId: $comparison->id,
            clearExisting: (bool) $clear,
            replaceExisting: (bool) $replace,
            role: $roleParam
        );

        return response()->json([
            'status'  => 'queued',
            'role'    => $roleParam,
            'message' => $roleParam
                ? "Pagination en file d'attente pour la version {$roleParam}."
                : "Pagination en file d'attente pour cette comparaison.",
        ], 202);
    }

    /**
     * Build pagination sidecar(s) from pb tags present in comparison XHTML outputs.
     */
    public function buildPaginationFromXhtml(Request $request, Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);
        $this->assertComparisonUnpublished($comparison);
        $validated = $request->validate([
            'role' => 'nullable|in:source,target',
        ]);

        $role = $validated['role'] ?? null;

        $result = $this->pageMarkerService->createSidecarFromComparisonOutputs($comparison, $role);
        $details = $result['details'] ?? [];

        $okDetails = array_values(array_filter($details, fn ($d) => ($d['status'] ?? '') === 'ok'));
        if (empty($okDetails)) {
            return response()->json([
                'status'  => 'error',
                'details' => $details,
                'message' => 'Impossible de générer le sidecar depuis les fichiers XHTML.',
            ], 422);
        }

        return response()->json([
            'status'  => $result['status'] ?? 'ok',
            'details' => $details,
            'message' => 'Sidecar généré à partir des fichiers comparison XHTML.',
        ], 200);
    }

    public function pageMarkersProgress(Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        $minUpdatedAt = $comparison->created_at ? $comparison->created_at->getTimestamp() : null;
        $snapshot = $this->pageMarkerService->getComparisonProgressSnapshot($comparison->id, $minUpdatedAt);
        if (!$snapshot) {
            $snapshot = [
                'status'        => 'idle',
                'comparison_id' => $comparison->id,
                'roles'         => [],
                'updated_at'    => time(),
            ];
        }

        return response()->json($snapshot, 200);
    }

    public function restorePageMarkers(Request $request, Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);
        $this->assertComparisonUnpublished($comparison);
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        try {
            $roleParam = strtolower((string) $request->input('role', ''));
            $roleParam = $roleParam !== '' ? $roleParam : null;
            if ($roleParam && !in_array($roleParam, ['source', 'target'], true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Rôle invalide. Utilisez "source" ou "target".',
                ], 422);
            }

            $result = $this->pageMarkerService->restoreOriginalComparisonOutputs($comparison, $roleParam);
        } catch (\Throwable $e) {
            Log::error('Unable to restore comparison originals', [
                'comparison_id' => $comparison->id,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Restauration impossible : ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'  => 'restored',
            'role'    => $roleParam,
            'message' => 'Fichiers originaux restaurés.',
            'result'  => $result,
        ]);
    }

    public function cancelPageMarkers(Request $request, Comparison $comparison)
    {
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);
        $comparison->loadMissing('sourceVersion', 'targetVersion');

        $roleParam = strtolower((string) $request->input('role', ''));
        $roleParam = $roleParam !== '' ? $roleParam : null;
        if ($roleParam && !in_array($roleParam, ['source', 'target'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Rôle invalide. Utilisez "source" ou "target".',
            ], 422);
        }

        $reason = 'Annulé par l\'utilisateur';

        try {
            $this->flushPendingPaginationJobs($comparison->id, $roleParam);

            $progress = $this->pageMarkerService->cancelComparisonProgress($comparison, $roleParam, $reason);
        } catch (\Throwable $e) {
            Log::error('Unable to cancel comparison pagination', [
                'comparison_id' => $comparison->id,
                'role'          => $roleParam,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Impossible d\'annuler la pagination : ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'   => 'cancelled',
            'role'     => $roleParam,
            'message'  => 'Pagination annulée.',
            'progress' => $progress,
        ]);
    }

    private function flushPendingPaginationJobs(int $comparisonId, ?string $role = null): void
    {
        $jobsQuery = DB::table('jobs')
            ->where('queue', 'page-markers')
            ->where('payload', 'like', '%inject-pagination%')
            ->where('payload', 'like', '%"comparisonId":' . $comparisonId . '%');
        if ($role) {
            $jobsQuery->where('payload', 'like', '%"role":"' . $role . '"%');
        }
        $jobsQuery->delete();

        $failedQuery = DB::table('failed_jobs')
            ->where('queue', 'page-markers')
            ->where('payload', 'like', '%inject-pagination%')
            ->where('payload', 'like', '%"comparisonId":' . $comparisonId . '%');
        if ($role) {
            $failedQuery->where('payload', 'like', '%"role":"' . $role . '"%');
        }
        $failedQuery->delete();

        if ($role) {
            Cache::forget('laravel_unique_job:inject-pagination-' . $comparisonId . '-' . $role);
            Cache::forget('laravel_unique_job:inject-pagination-' . $comparisonId);
        } else {
            foreach (['all', 'source', 'target'] as $suffix) {
                Cache::forget('laravel_unique_job:inject-pagination-' . $comparisonId . '-' . $suffix);
            }
            Cache::forget('laravel_unique_job:inject-pagination-' . $comparisonId);
        }
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

        $scope = $comparison->publication_scope ?? null;
        if (!$scope && $comparison->is_legacy) {
            $scope = 'prod';
        }

        $published = $scope !== null;
        if (!$published) {
            $published = true;
            foreach (PublishController::COMPONENTS as $file) {
                if (!Storage::disk('public')->exists("{$destDir}/{$file}")) {
                    $published = false;
                    break;
                }
            }
        }

        return [
            'source_dir' => $sourceDir,
            'dest_dir'   => $destDir,
            'published'  => $published,
        ];
    }

    private function exportBundlePayload(Comparison $comparison): array
    {
        $snapshot = $this->legacyExportService->getSnapshot($comparison->id);
        $status = $snapshot['status'] ?? 'idle';

        return [
            ...$snapshot,
            'status' => $status,
            'download_url' => $status === 'ready'
                ? admin_url("comparisons/{$comparison->id}/export/download")
                : null,
            'status_url' => admin_url("comparisons/{$comparison->id}/export/status"),
            'queue_url' => admin_url("comparisons/{$comparison->id}/export"),
        ];
    }

    private function manifestStatusForComparison(Comparison $comparison, ?string $authorFolder, ?string $workFolder): array
    {
        $status = [
            'source' => ['exists' => false, 'count' => 0, 'file' => null, 'url' => null, 'api_url' => null],
            'target' => ['exists' => false, 'count' => 0, 'file' => null, 'url' => null, 'api_url' => null],
        ];

        if (!$authorFolder || !$workFolder || empty($comparison->folder)) {
            return $status;
        }

        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));
        $disk = Storage::disk('public');

        foreach ([
            'source' => $comparison->sourceVersion?->folder,
            'target' => $comparison->targetVersion?->folder,
        ] as $role => $versionFolder) {
            if (!$versionFolder) {
                continue;
            }

            $relativeDir  = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
            $filename     = sprintf('images_%s_%s.json', $role, $baseName);
            $relativePath = "{$relativeDir}/{$filename}";

            $entries = null;
            $exists  = false;
            $url     = null;
            $apiUrl  = null;

            if ($disk->exists($relativePath)) {
                $exists = true;
                try {
                    $entries = json_decode($disk->get($relativePath), true);
                } catch (\Throwable $e) {
                    $entries = null;
                }

                try {
                    $url = $disk->url($relativePath);
                } catch (\Throwable $e) {
                    $url = null;
                }
            } else {
                $legacyPath = base_path('../variance/' . $relativePath);
                if (is_file($legacyPath)) {
                    $exists = true;
                    try {
                        $entries = json_decode(File::get($legacyPath), true);
                    } catch (\Throwable $e) {
                        $entries = null;
                    }
                }
            }

            try {
                $apiUrl = route('comparisons.manifest', [
                    'comparison' => $comparison->id,
                    'role'       => $role,
                ]);
            } catch (\Throwable $e) {
                $apiUrl = null;
            }

            $status[$role] = [
                'exists' => $exists,
                'count'  => is_array($entries) ? count($entries) : 0,
                'file'   => $exists ? $relativePath : null,
                'url'    => $url,
                'api_url'=> $apiUrl,
            ];
        }

        return $status;
    }

    private function resolveManifestFolders(Comparison $comparison): array
    {
        $workInfo = DB::table('versions')
            ->where('versions.id', $comparison->source_id)
            ->join('works', 'versions.work_id', '=', 'works.id')
            ->join('authors', 'works.author_id', '=', 'authors.id')
            ->select(
                'works.folder as work_folder',
                'authors.folder as author_folder'
            )
            ->first();

        if (!$workInfo || !$workInfo->author_folder || !$workInfo->work_folder) {
            return [null, null];
        }

        return [$workInfo->author_folder, $workInfo->work_folder];
    }

    private function assertComparisonEditable(Comparison $comparison): void
    {
        $comparison->loadMissing('sourceVersion.work', 'targetVersion.work');

        $sourceVersion = $comparison->sourceVersion;
        $targetVersion = $comparison->targetVersion;

        if ($comparison->is_legacy
            || $sourceVersion?->is_legacy
            || $targetVersion?->is_legacy
            || $sourceVersion?->work?->is_legacy
            || $targetVersion?->work?->is_legacy) {
            abort(403, 'Cette comparaison est en lecture seule.');
        }
    }

    private function assertComparisonUnpublished(Comparison $comparison): void
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $destInfo = $this->resolvePublicationPaths($comparison);
        if (!empty($destInfo['published'])) {
            abort(409, 'Cette comparaison est publiée. Dépubliez-la avant toute modification.');
        }
    }

    private function scopeComparisonsToUser($query, ?User $user): void
    {
        if ($user && ! $user->is_admin) {
            $query->where(function ($inner) use ($user) {
                $inner->where('created_by', $user->id)
                    ->orWhere('is_legacy', true);
            });
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

    public function showManifest(Comparison $comparison, string $role)
    {
        $this->assertComparisonOwnership($comparison);
        $role = strtolower($role);
        if (!in_array($role, ['source', 'target'], true)) {
            abort(404);
        }

        $comparison->loadMissing('sourceVersion', 'targetVersion');
        [$authorFolder, $workFolder] = $this->resolveManifestFolders($comparison);

        if (!$authorFolder || !$workFolder) {
            abort(404);
        }

        $manifests = $this->manifestStatusForComparison($comparison, $authorFolder, $workFolder);
        $entry = $manifests[$role] ?? null;

        if (!$entry || !$entry['exists']) {
            abort(404);
        }

        $relative = $entry['file'];
        if ($relative && Storage::disk('public')->exists($relative)) {
            $content = Storage::disk('public')->get($relative);
            return response($content, 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        if ($relative) {
            $legacyPath = base_path('../variance/' . $relative);
            if (is_file($legacyPath)) {
                return response(File::get($legacyPath), 200, [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ]);
            }
        }

        abort(404);
    }
}
