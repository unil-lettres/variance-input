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
use App\Http\Controllers\PublishController;
use App\Services\PageMarkerService;
use App\Jobs\InjectComparisonPaginationJob;

class ComparisonController extends Controller
{
    public function __construct(private PageMarkerService $pageMarkerService)
    {
    }

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
        $workFolder = $workInfo->folder ?? null;

        $required = PublishController::COMPONENTS;

        $comparisons = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])
            ->whereIn('source_id', $versionIds)
            ->orWhereIn('target_id', $versionIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Comparison $cmp) use ($destBase, $required, $authorFolder, $workFolder) {
                // Do not inject default pagination markers anymore; rely on real facsimiles/_lignes only

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

                $cmp->pagination = $this->pageMarkerService->countMarkersForComparison($cmp);
                $cmp->manifests  = $this->manifestStatusForComparison($cmp, $authorFolder, $workFolder);
                $cmp->comparison_progress = $this->pageMarkerService->getComparisonProgressSnapshot($cmp->id);

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
        $comparison = Comparison::with([
            'sourceVersion.work.author',
            'targetVersion.work.author',
        ])->findOrFail($id);

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

        // Remove DB record
        $comparison->delete();

        return response()->json(['message' => 'Comparison deleted']);
    }

    public function applyPageMarkers(Request $request, Comparison $comparison)
    {
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
        foreach ($selectedRoles as $role => $version) {
            if (!$version) {
                $missing[] = "Version {$role} manquante";
                continue;
            }
            if (!$this->pageMarkerService->loadPaginationSidecar($version->id)) {
                $missing[] = "Sidecar pagination manquant pour la version {$version->name}";
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'status'  => 'error',
                'message' => implode(' | ', $missing),
            ], 422);
        }

        foreach ($selectedRoles as $role => $version) {
            if ($version) {
                $this->pageMarkerService->markQueued($version->id);
            }
        }

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

    public function pageMarkersProgress(Comparison $comparison)
    {
        $snapshot = $this->pageMarkerService->getComparisonProgressSnapshot($comparison->id);
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

    public function showManifest(Comparison $comparison, string $role)
    {
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
