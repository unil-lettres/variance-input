<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Jobs\InjectComparisonPaginationJob;
use App\Models\Comparison;
use App\Models\Version;
use App\Models\Work;
use App\Services\PageMarkerService;

class MediteController extends Controller
{
    public function __construct(private PageMarkerService $pageMarkerService)
    {
    }

    /**
     * POST /api/comparisons
     * Create (or reuse) a comparison row with full Medite metadata.
     */
    public function createComparison(Request $request)
    {
        /* ─── 1. Validate input ───────────────────────────────────────────── */
        $data = $request->validate([
            /* mandatory */
            'source_id' => 'required|exists:versions,id',
            'target_id' => 'required|exists:versions,id',
            'folder'    => 'required|string|max:255',

            /* optional but accepted */
            'lg_pivot'         => 'nullable|integer',
            'ratio'            => 'nullable|integer',
            'sep'              => 'nullable|string|max:50',
            'case_sensitive'   => 'nullable|boolean',
        ]);

        Log::debug('createComparison payload', $data);

        $this->assertVersionsEditable((int) $data['source_id'], (int) $data['target_id']);

        $sep = array_key_exists('sep', $data) ? $data['sep'] : null;
        if ($sep === '') {
            $sep = null;
        }

        /* ─── 2. Insert a new row (fill every NOT-NULL col) ──────────────── */
        [ $folder, $sequence ] = $this->nextFolderAndNumber(
            $data['folder'],
            (int) $data['source_id'],
            (int) $data['target_id']
        );

        $payload = [
            'source_id'        => $data['source_id'],
            'target_id'        => $data['target_id'],
            'folder'           => $folder,

            /* Medite parameters (fallback to sensible defaults) */
            'lg_pivot'         => $data['lg_pivot']         ?? 7,
            'ratio'            => $data['ratio']            ?? 15,
            'case_sensitive'   => $data['case_sensitive']   ?? false,
            'diacri_sensitive' => true,

            /* house-keeping */
            'prefix_label'     => 'Auto',
            'number'           => $sequence,
        ];

        if (Schema::hasColumn('comparisons', 'created_by')) {
            $payload['created_by'] = $this->resolveCreatorId();
        }

        if (Schema::hasColumn('comparisons', 'sep')) {
            $payload['sep'] = $sep;
        }

        $cmp = Comparison::create($payload);
        $this->pageMarkerService->clearComparisonProgress($cmp->id);

        return response()->json($cmp, 201);                // Created
    }


    /*──────────────────────── 2.  Run Medite (Flask) ───────────────────────*/
    public function runMedite(Request $request)
    {
        /* ───── Validation ───── */
        $validated = $request->validate([
            'source_version' => 'required|exists:versions,id',
            'target_version' => 'required|exists:versions,id',
            'work_id'        => 'required|exists:works,id',
            'lg_pivot'       => 'required|integer',
            'ratio'          => 'required|integer',
            'sep'            => 'nullable|string',
            'comparison_id'  => 'nullable|exists:comparisons,id',
        ]);

        $this->assertVersionsEditable(
            (int) $validated['source_version'],
            (int) $validated['target_version'],
            (int) $validated['work_id']
        );

        $caseSensitive   = $request->has('case_sensitive');
        $diacriSensitive = true;

        $rawSep = $request->input('sep', null);
        if ($rawSep === '') {
            $rawSep = null;
        }
        $separators = $rawSep;

        $sourceVersion = Version::with('work.author')->findOrFail((int) $validated['source_version']);
        $targetVersion = Version::with('work.author')->findOrFail((int) $validated['target_version']);

        /* ───── Short names for versions ───── */
        $sourceShort = $sourceVersion->folder;
        $targetShort = $targetVersion->folder;
        $comparisonShort = "$sourceShort-$targetShort";

        /* ───── Create or update comparison ───── */
        $comparisonPayload = [
            'source_id'        => $validated['source_version'],
            'target_id'        => $validated['target_version'],
            'lg_pivot'         => $validated['lg_pivot'],
            'ratio'            => $validated['ratio'],
            'case_sensitive'   => $caseSensitive,
            'diacri_sensitive' => $diacriSensitive,
        ];

        if (Schema::hasColumn('comparisons', 'sep')) {
            $comparisonPayload['sep'] = $separators;
        }

        $comparisonId = $validated['comparison_id'] ?? null;

        if ($comparisonId) {
            $cmp = Comparison::findOrFail($comparisonId);
            $this->assertComparisonOwnership($cmp);
            $cmp->fill($comparisonPayload);

            if (empty($cmp->folder)) {
                [ $folder, $sequence ] = $this->nextFolderAndNumber(
                    $comparisonShort,
                    (int) $validated['source_version'],
                    (int) $validated['target_version'],
                    $cmp->id
                );
                $cmp->folder = $folder;
                $cmp->number = $sequence;
            }

            if (!$cmp->prefix_label) {
                $cmp->prefix_label = 'Auto Run';
            }

            if (!$cmp->number) {
                [ $_folder, $sequence ] = $this->nextFolderAndNumber(
                    $comparisonShort,
                    (int) $validated['source_version'],
                    (int) $validated['target_version'],
                    $cmp->id
                );
                $cmp->number = $sequence;
            }

            $cmp->save();
        } else {
            [ $folder, $sequence ] = $this->nextFolderAndNumber(
                $comparisonShort,
                (int) $validated['source_version'],
                (int) $validated['target_version']
            );

            $createPayload = $comparisonPayload + [
                'folder'       => $folder,
                'prefix_label' => 'Auto Run',
                'number'       => $sequence,
            ];

            if (Schema::hasColumn('comparisons', 'created_by')) {
                $createPayload['created_by'] = $this->resolveCreatorId();
            }

            $cmp = Comparison::create($createPayload);
        }
        $this->pageMarkerService->clearComparisonProgress($cmp->id);

        /* ───── Paths for Flask outputs ───── */
        $workRow = DB::table('works')
            ->select('id', 'folder', 'title', 'author_id')
            ->where('id', $validated['work_id'])
            ->first();

        if (!$workRow) {
            return response()->json([
                'error' => 'Œuvre introuvable pour cette comparaison.'
            ], 422);
        }

        $authorFolder = DB::table('authors')
            ->where('id', $workRow->author_id)
            ->value('folder') ?? 'author';

        $workFolder = $workRow->folder;
        if (!$workFolder) {
            $workFolder = Str::slug($workRow->title ?? 'work') ?: 'work';
        }

        $baseDir   = "/app/uploads/{$authorFolder}/{$workFolder}/comparisons/{$cmp->id}";
        $outputXml = "{$baseDir}/{$sourceShort}-{$targetShort}.xml";

        /* ───── Source / Target absolute paths ───── */
        $sourceFile = $this->convertPath($sourceShort);
        $targetFile = $this->convertPath($targetShort);

        $payload = [
            'source_filename'   => $sourceFile,
            'target_filename'   => $targetFile,
            'lg_pivot'          => $validated['lg_pivot'],
            'ratio'             => $validated['ratio'],
            'case_sensitive'    => $caseSensitive ? 'on' : 'off',
            'diacri_sensitive'  => $diacriSensitive ? 'on' : 'off',
            'output_xml'        => $outputXml,
            'xhtml_output_dir'  => $baseDir,
            'comparison_id'     => $cmp->id,
        ];

        if ($separators !== null) {
            $payload['sep'] = $separators;
        }

        /* ───── Call Flask ───── */
        try {
            $resp = Http::timeout(120)
                        ->asForm()
                        ->post('http://medite:5000/run_diff2', $payload);

            if ($resp->successful()) {
                return response()->json([
                    'task_id'       => $resp->json('task_id'),
                    'comparison_id' => $cmp->id,
                ]);
            }

            return response()->json([
                'error'   => 'Flask error',
                'details' => $resp->json(),
            ], $resp->status());
        } catch (\Exception $e) {
            Log::error('Medite call failed', ['e' => $e]);
            return response()->json([
                'error'   => 'Could not contact Medite',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /*──────────────────────── 3.  Poll Celery ───────────────────────*/
    public function taskStatus($taskId)
    {
        try {
            $r = Http::get("http://medite:5000/task_status/{$taskId}");
            $payload = $r->json();
            if (($payload['status'] ?? null) === 'completed') {
                $metrics = $payload['result']['metrics'] ?? null;
                $comparisonId = $metrics['comparison_id'] ?? $payload['result']['comparison_id'] ?? null;
                if ($comparisonId) {
                    $updates = [];
                    $runtimeSeconds = $metrics['runtime_seconds'] ?? null;
                    $peakRssKb = $metrics['peak_rss_kb'] ?? null;
                    if (is_numeric($runtimeSeconds)) {
                        $updates['medite_runtime_ms'] = (int) round(((float) $runtimeSeconds) * 1000);
                    }
                    if (is_numeric($peakRssKb)) {
                        $updates['medite_peak_rss_kb'] = (int) round((float) $peakRssKb);
                    }
                    if ($updates) {
                        Comparison::whereKey($comparisonId)->update($updates);
                    }

                    $dispatchKey = "medite:auto-pagination:{$comparisonId}:{$taskId}";
                    if (Cache::add($dispatchKey, true, now()->addMinutes(30))) {
                        $comparison = Comparison::with(['sourceVersion', 'targetVersion'])->find($comparisonId);
                        if ($comparison) {
                            $roles = [
                                'source' => $comparison->sourceVersion,
                                'target' => $comparison->targetVersion,
                            ];

                            $queuedRoles = [];
                            foreach ($roles as $role => $version) {
                                if (!$version) {
                                    continue;
                                }

                                $sidecar = $this->pageMarkerService->loadPaginationSidecar($version->id);
                                if (!$sidecar) {
                                    continue;
                                }

                                $queuedRoles[$role] = [
                                    'version_id' => $version->id,
                                    'total'      => is_array($sidecar['markers'] ?? null)
                                        ? count($sidecar['markers'])
                                        : (int) ($sidecar['marker_count'] ?? 0),
                                ];
                            }

                            if (!empty($queuedRoles)) {
                                $this->pageMarkerService->markComparisonQueued($comparison->id, $queuedRoles);

                                if (count($queuedRoles) === 2) {
                                    InjectComparisonPaginationJob::dispatch(
                                        comparisonId: (int) $comparison->id,
                                        clearExisting: true,
                                        replaceExisting: true,
                                        role: null
                                    );
                                } else {
                                    $role = array_key_first($queuedRoles);
                                    InjectComparisonPaginationJob::dispatch(
                                        comparisonId: (int) $comparison->id,
                                        clearExisting: true,
                                        replaceExisting: true,
                                        role: $role
                                    );
                                }
                            }
                        }
                    }
                }
            }
            return response()->json($payload, $r->successful() ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'failed',
                'error'   => 'Exception',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*──────────────────────── Helper ───────────────────────*/
    private function convertPath(string $short): string
    {
        $storageRoot = storage_path('app/public');
        $localPath   = storage_path("app/public/uploads/versions/{$short}.xml");
        $publicPath  = public_path("uploads/versions/{$short}.xml");

        if (file_exists($localPath)) {
            $relative = substr($localPath, strlen($storageRoot));
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            return "/app/storage_public{$relative}";
        }

        if (file_exists($publicPath)) {
            return "/app/uploads/versions/{$short}.xml";
        }

        Log::warning('Medite source file missing from storage', [
            'short'       => $short,
            'storagePath' => $localPath,
            'publicPath'  => $publicPath,
        ]);

        return "/app/storage_public/uploads/versions/{$short}.xml";
    }

    private function nextFolderAndNumber(string $base, int $sourceId, int $targetId, ?int $excludeId = null): array
    {
        $slug = Str::slug($base, '-');
        if ($slug === '') {
            $slug = 'comparison';
        }

        $pairQuery = Comparison::where('source_id', $sourceId)
            ->where('target_id', $targetId);

        if ($excludeId) {
            $pairQuery->where('id', '!=', $excludeId);
        }

        $pairSequence = (int) $pairQuery->max('number');
        if ($pairSequence <= 0) {
            $pairSequence = (int) $pairQuery->count();
        }
        $pairSequence += 1;

        $workId = Version::where('id', $sourceId)->value('work_id');

        $orderQuery = Comparison::query();
        if ($workId) {
            $orderQuery->whereHas('sourceVersion', fn ($q) => $q->where('work_id', $workId));
        } else {
            $orderQuery->where('source_id', $sourceId)
                ->where('target_id', $targetId);
        }

        if ($excludeId) {
            $orderQuery->where('id', '!=', $excludeId);
        }

        $number = (int) $orderQuery->max('number');
        if ($number <= 0) {
            $number = (int) $orderQuery->count();
        }
        $number += 1;

        $suffix = "run{$pairSequence}";
        $separator = '-';
        $maxBaseLength = 45 - strlen($suffix) - strlen($separator);

        if ($maxBaseLength < 1) {
            $folder = Str::limit($suffix, 45, '');
        } else {
            $basePart = substr($slug, 0, $maxBaseLength);
            $folder = $basePart !== ''
                ? "{$basePart}{$separator}{$suffix}"
                : Str::limit($suffix, 45, '');
        }

        while (
            Comparison::query()
                ->where('folder', $folder)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $pairSequence += 1;
            $suffix = "run{$pairSequence}";
            if ($maxBaseLength < 1) {
                $folder = Str::limit($suffix, 45, '');
            } else {
                $basePart = substr($slug, 0, $maxBaseLength);
                $folder = $basePart !== ''
                    ? "{$basePart}{$separator}{$suffix}"
                    : Str::limit($suffix, 45, '');
            }
        }

        return [$folder, $number];
    }

    private function assertVersionsEditable(int $sourceId, int $targetId, ?int $workId = null): void
    {
        $versions = Version::with('work')
            ->whereIn('id', [$sourceId, $targetId])
            ->get();

        foreach ($versions as $version) {
            if (($version->is_legacy || $version->work?->is_legacy) && !$this->hasMediteXmlInput($version->folder)) {
                abort(422, 'Impossible de lancer Medite pour une version legacy sans fichier TEI-XML.');
            }
        }

        if ($workId !== null) {
            $work = Work::find($workId);
            if ($work?->is_legacy && $versions->contains(fn (Version $version) => !$this->hasMediteXmlInput($version->folder))) {
                abort(422, 'Impossible de lancer Medite pour une œuvre legacy dont une version ne dispose pas de fichier TEI-XML.');
            }
        }
    }

    private function hasMediteXmlInput(string $short): bool
    {
        return file_exists(storage_path("app/public/uploads/versions/{$short}.xml"))
            || file_exists(public_path("uploads/versions/{$short}.xml"));
    }

    private function resolveCreatorId(): ?int
    {
        return auth()->id();
    }

    private function assertComparisonOwnership(Comparison $comparison): void
    {
        $user = auth()->user();
        if (!$user || $user->is_admin) {
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
