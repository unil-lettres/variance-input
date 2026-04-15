<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Comparison;
use App\Models\Work;
use App\Services\ChapterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChaptersController extends Controller
{
    public function __construct(private ChapterImportService $chapterImportService)
    {
    }

    public function targets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_id' => 'required|exists:works,id',
        ]);

        $work = Work::with('author')->findOrFail($validated['work_id']);
        $this->assertWorkChapterReadable($work);

        $chapterCounts = Chapter::query()
            ->select('folder', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('folder')
            ->pluck('aggregate', 'folder');

        $comparisons = Comparison::query()
            ->with(['sourceVersion', 'targetVersion'])
            ->where(function ($query) use ($work) {
                $query->whereHas('sourceVersion', fn ($q) => $q->where('work_id', $work->id))
                    ->orWhereHas('targetVersion', fn ($q) => $q->where('work_id', $work->id));
            })
            ->where(function ($query) use ($chapterCounts) {
                $query->where('is_legacy', false);

                if ($chapterCounts->isNotEmpty()) {
                    $query->orWhereIn('folder', $chapterCounts->keys()->all());
                }
            })
            ->orderByRaw('CASE WHEN number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('number')
            ->orderBy('id')
            ->get()
            ->map(function (Comparison $comparison) use ($chapterCounts) {
                $sourceName = trim((string) ($comparison->sourceVersion?->name ?? ''));
                $targetName = trim((string) ($comparison->targetVersion?->name ?? ''));
                $label = collect([$sourceName, $targetName])->filter()->join(' - ');
                if ($label === '') {
                    $label = $comparison->folder ?: ('#' . $comparison->id);
                }

                return [
                    'id' => $comparison->id,
                    'folder' => $comparison->folder,
                    'label' => $label,
                    'published' => $comparison->publication_scope !== null,
                    'readonly' => (bool) $comparison->is_legacy,
                    'chapter_count' => (int) ($chapterCounts[$comparison->folder] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'work' => [
                'id' => $work->id,
                'title' => $work->title,
                'folder' => $work->folder,
                'author' => $work->author?->name,
            ],
            'targets' => $comparisons,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'comparison_id' => 'required|exists:comparisons,id',
            'file' => 'required|file|mimes:xlsx|max:5120',
        ]);

        $comparison = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])->findOrFail($validated['comparison_id']);
        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        abort_unless($work, 422, 'Œuvre introuvable pour cette comparaison.');
        $this->assertWorkEditable($work);
        abort_if($comparison->is_legacy, 403, 'Les comparaisons legacy restent en lecture seule.');

        $workbook = $this->chapterImportService->parseWorkbook($request->file('file')->getRealPath());
        $preview = $this->chapterImportService->buildPreview($workbook['rows']);

        $token = Str::uuid()->toString();
        $cacheKey = $this->previewCacheKey((int) $request->user()->id, $token);
        Cache::put($cacheKey, [
            'comparison_id' => $comparison->id,
            'folder' => $comparison->folder,
            'preview' => $preview,
        ], now()->addMinutes(30));

        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'comparison' => [
                'id' => $comparison->id,
                'folder' => $comparison->folder,
            ],
            'sheet_name' => $workbook['sheet_name'],
            'header' => $preview['header'],
            'rows' => $preview['rows'],
            'warnings' => $preview['warnings'],
            'summary' => $preview['summary'] + [
                'existing_count' => Chapter::query()->forFolder($comparison->folder)->count(),
            ],
        ]);
    }

    public function show(Request $request, Comparison $comparison): JsonResponse
    {
        $comparison->loadMissing(['sourceVersion.work.author', 'targetVersion.work.author']);
        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        abort_unless($work, 422, 'Œuvre introuvable pour cette comparaison.');
        $this->assertWorkChapterReadable($work);

        $rows = Chapter::query()
            ->forFolder($comparison->folder)
            ->orderBy('id')
            ->get()
            ->map(function (Chapter $chapter) {
                return [
                    'row_number' => $chapter->id,
                    'level' => $chapter->level,
                    'label' => $chapter->label_source !== '' ? $chapter->label_source : $chapter->label_target,
                    'label_source' => $chapter->label_source,
                    'label_target' => $chapter->label_target,
                    'parent_level' => $chapter->parent?->level,
                    'start_line_source' => $chapter->start_line_source,
                    'start_line_target' => $chapter->start_line_target,
                ];
            })
            ->values();

        return response()->json([
            'status' => 'ok',
            'comparison' => [
                'id' => $comparison->id,
                'folder' => $comparison->folder,
                'readonly' => (bool) $comparison->is_legacy,
            ],
            'rows' => $rows,
            'summary' => [
                'count' => $rows->count(),
                'root_count' => $rows->where('parent_level', null)->count(),
            ],
        ]);
    }

    public function commit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'comparison_id' => 'required|exists:comparisons,id',
            'token' => 'required|string',
        ]);

        $comparison = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])->findOrFail($validated['comparison_id']);
        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        abort_unless($work, 422, 'Œuvre introuvable pour cette comparaison.');
        $this->assertWorkEditable($work);
        abort_if($comparison->is_legacy, 403, 'Les comparaisons legacy restent en lecture seule.');

        $cacheKey = $this->previewCacheKey((int) $request->user()->id, $validated['token']);
        $cached = Cache::get($cacheKey);
        abort_unless(is_array($cached), 410, 'Aperçu expiré. Rechargez le fichier.');
        abort_if((int) ($cached['comparison_id'] ?? 0) !== (int) $comparison->id, 422, 'Le jeton d’import ne correspond pas à cette comparaison.');

        $rows = $cached['preview']['rows'] ?? [];
        abort_if(!is_array($rows) || $rows === [], 422, 'Aucune donnée à importer.');

        $imported = DB::transaction(function () use ($comparison, $rows) {
            Chapter::query()->forFolder($comparison->folder)->delete();

            $createdByLevel = [];
            $created = 0;
            foreach ($rows as $row) {
                $chapter = Chapter::create([
                    'folder' => $comparison->folder,
                    'level' => $row['level'],
                    'label_source' => $row['label_source'] ?? '',
                    'label_target' => $row['label_target'] ?? '',
                    'chapter_parent' => $row['parent_level'] ? ($createdByLevel[$row['parent_level']] ?? null) : 0,
                    'start_line_source' => $row['start_line_source'] ?? '',
                    'start_line_target' => $row['start_line_target'] ?? '',
                    'id_tome_source' => $row['id_tome_source'] ?? 0,
                    'id_tome_target' => $row['id_tome_target'] ?? 0,
                ]);

                if ($row['parent_level'] === null && !isset($createdByLevel[$row['level']])) {
                    $createdByLevel[$row['level']] = $chapter->id;
                }
                if ($row['parent_level'] !== null) {
                    $createdByLevel[$row['level']] = $chapter->id;
                }

                $created++;
            }

            return $created;
        });

        Cache::forget($cacheKey);

        return response()->json([
            'status' => 'ok',
            'comparison_id' => $comparison->id,
            'folder' => $comparison->folder,
            'imported_count' => $imported,
        ]);
    }

    private function assertWorkEditable(Work $work): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_if(!$user->can('edit', $work), 403, 'Vous n’avez pas la permission de modifier les chapitres de cette œuvre.');
    }

    private function assertWorkChapterReadable(Work $work): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->is_admin) {
            return;
        }

        $hasWorkPermission = $user->permissions()
            ->where('work_id', $work->id)
            ->where('permission_type', 'edit')
            ->exists();

        $hasAuthorPermission = $user->permissions()
            ->where('author_id', $work->author_id)
            ->where('permission_type', 'edit')
            ->exists();

        abort_if(!$hasWorkPermission && !$hasAuthorPermission, 403, 'Vous n’avez pas la permission de consulter les chapitres de cette œuvre.');
    }

    private function previewCacheKey(int $userId, string $token): string
    {
        return "chapters-import-preview:{$userId}:{$token}";
    }
}
