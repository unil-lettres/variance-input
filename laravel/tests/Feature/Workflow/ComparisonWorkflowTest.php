<?php

namespace Tests\Feature\Workflow;

use App\Models\Chapter;
use App\Models\Comparison;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ComparisonWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_medite_creates_owned_comparison_and_calls_medite_service(): void
    {
        Http::fake([
            'http://medite:5000/run_diff2' => Http::response([
                'task_id' => 'task-123',
            ], 200),
        ]);

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Comparaison témoin',
            'short_title' => 'ctm',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Édition A',
            'folder' => '1ctm',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Édition B',
            'folder' => '2ctm',
        ]);
        $this->writeVersionXml($source);
        $this->writeVersionXml($target);

        $response = $this->postJson('/api/run_medite', [
            'source_version' => $source->id,
            'target_version' => $target->id,
            'work_id' => $work->id,
            'lg_pivot' => 9,
            'ratio' => 21,
            'sep' => 'default',
            'case_sensitive' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('task_id', 'task-123');

        $comparisonId = (int) $response->json('comparison_id');
        $comparison = Comparison::findOrFail($comparisonId);

        $this->assertSame($user->id, $comparison->created_by);
        $this->assertSame(9, $comparison->lg_pivot);
        $this->assertSame(21, $comparison->ratio);
        $this->assertTrue($comparison->case_sensitive);
        $this->assertStringEndsWith('run1', $comparison->folder);

        $expectedSourcePath = "/app/uploads/__medite_inputs/{$comparisonId}/1ctm.xml";
        $expectedTargetPath = "/app/uploads/__medite_inputs/{$comparisonId}/2ctm.xml";
        $hostSourcePath = base_path("../variance/uploads/__medite_inputs/{$comparisonId}/1ctm.xml");
        $hostTargetPath = base_path("../variance/uploads/__medite_inputs/{$comparisonId}/2ctm.xml");

        Http::assertSent(function ($request) use ($comparisonId, $expectedSourcePath, $expectedTargetPath) {
            return $request->url() === 'http://medite:5000/run_diff2'
                && $request['comparison_id'] === $comparisonId
                && $request['lg_pivot'] === 9
                && $request['ratio'] === 21
                && $request['source_filename'] === $expectedSourcePath
                && $request['target_filename'] === $expectedTargetPath;
        });

        $this->assertTrue(File::exists($hostSourcePath));
        $this->assertTrue(File::exists($hostTargetPath));
    }

    public function test_task_status_persists_medite_metrics_on_completion(): void
    {
        $this->signInEditor();
        $work = $this->createEditableWork(auth()->user(), [], [
            'title' => 'Mesures Medite',
            'short_title' => 'msm',
        ]);
        $source = Version::factory()->for($work)->create([
            'folder' => '1msm',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => '2msm',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'created_by' => auth()->id(),
        ]);

        Http::fake([
            'http://medite:5000/task_status/*' => Http::response([
                'status' => 'completed',
                'result' => [
                    'comparison_id' => $comparison->id,
                    'metrics' => [
                        'comparison_id' => $comparison->id,
                        'runtime_seconds' => 1.432,
                        'peak_rss_kb' => 20480,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/task_status/task-123');

        $response->assertOk()
            ->assertJsonPath('status', 'completed');

        $comparison->refresh();

        $this->assertSame(1432, $comparison->medite_runtime_ms);
        $this->assertSame(20480, $comparison->medite_peak_rss_kb);
    }

    public function test_work_editor_sees_all_non_legacy_comparisons_for_assigned_work(): void
    {
        $owner = $this->signInEditor();
        $work = $this->createEditableWork($owner, [], [
            'title' => 'Visibilité comparaisons',
            'short_title' => 'vcp',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1vcp']);
        $target = Version::factory()->for($work)->create(['folder' => '2vcp']);

        $mine = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => 'mine-run1',
            'created_by' => $owner->id,
            'is_legacy' => false,
        ]);
        $other = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => 'other-run1',
            'created_by' => null,
            'is_legacy' => false,
        ]);
        $legacy = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => 'legacy-run1',
            'created_by' => null,
            'is_legacy' => true,
        ]);

        $response = $this->getJson("/comparisons/by-work?work_id={$work->id}&light=1");

        $response->assertOk()
            ->assertJsonCount(3);

        $ids = collect($response->json())->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$mine->id, $other->id, $legacy->id])->sort()->values()->all(),
            $ids
        );

        $payload = collect($response->json())->keyBy('id');
        $this->assertTrue($payload[$mine->id]['can_manage'] ?? false);
        $this->assertTrue($payload[$other->id]['can_manage'] ?? false);
        $this->assertFalse($payload[$legacy->id]['can_manage'] ?? true);

        $intruder = User::factory()->create(['is_admin' => false]);
        $this->actingAs($intruder);

        $response = $this->getJson("/comparisons/by-work?work_id={$work->id}&light=1");

        $response->assertOk()
            ->assertJsonCount(1);

        $this->assertSame([$legacy->id], collect($response->json())->pluck('id')->all());

        $this->actingAs($owner);

        Chapter::create([
            'folder' => $mine->folder,
            'level' => '1',
            'label_source' => 'Première partie',
            'label_target' => 'Première partie',
            'chapter_parent' => 0,
            'start_line_source' => '1a',
            'start_line_target' => '1a',
            'id_tome_source' => 0,
            'id_tome_target' => 0,
        ]);

        $response = $this->getJson("/comparisons/by-work?work_id={$work->id}&light=1");
        $payload = collect($response->json());
        $minePayload = $payload->firstWhere('id', $mine->id);
        $legacyPayload = $payload->firstWhere('id', $legacy->id);

        $this->assertSame(1, $minePayload['chapter_count'] ?? null);
        $this->assertTrue($minePayload['has_chapters'] ?? false);
        $this->assertSame(0, $legacyPayload['chapter_count'] ?? null);
        $this->assertFalse($legacyPayload['has_chapters'] ?? true);
    }
}
