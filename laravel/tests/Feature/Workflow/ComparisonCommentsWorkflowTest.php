<?php

namespace Tests\Feature\Workflow;

use App\Models\Chapter;
use App\Models\Comparison;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ComparisonCommentsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_comment_after_publication(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Commentaires de comparaison',
            'short_title' => 'cdc',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1cdc']);
        $target = Version::factory()->for($work)->create(['folder' => '2cdc']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cdc-2cdc-run1',
            'created_by' => $user->id,
            'publication_scope' => 'dev',
        ]);

        $response = $this->patchJson("/comparisons/{$comparison->id}/comments", [
            'comments' => 'Correction manuelle des marqueurs de pagination.',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('comments', 'Correction manuelle des marqueurs de pagination.')
            ->assertJsonPath('has_comments', true);

        $comparison->refresh();
        $this->assertSame('Correction manuelle des marqueurs de pagination.', $comparison->comments);
    }

    public function test_empty_comment_is_stored_as_null(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Commentaires effacés',
            'short_title' => 'cde',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1cde']);
        $target = Version::factory()->for($work)->create(['folder' => '2cde']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cde-2cde-run1',
            'created_by' => $user->id,
            'comments' => 'Ancien commentaire',
        ]);

        $response = $this->patchJson("/comparisons/{$comparison->id}/comments", [
            'comments' => '   ',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('comments', null)
            ->assertJsonPath('has_comments', false);

        $comparison->refresh();
        $this->assertNull($comparison->comments);
    }

    public function test_non_owner_cannot_update_comments(): void
    {
        $owner = User::factory()->create([
            'is_admin' => false,
            'password' => bcrypt('password'),
        ]);
        $other = $this->signInEditor();
        $work = $this->createEditableWork($owner, [], [
            'title' => 'Commentaires protégés',
            'short_title' => 'cdp',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1cdp']);
        $target = Version::factory()->for($work)->create(['folder' => '2cdp']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cdp-2cdp-run1',
            'created_by' => $owner->id,
        ]);

        $response = $this->patchJson("/comparisons/{$comparison->id}/comments", [
            'comments' => 'Tentative non autorisée',
        ]);

        $response->assertForbidden();

        $comparison->refresh();
        $this->assertNull($comparison->comments);
    }

    public function test_deleting_comparison_removes_chapter_rows_for_same_folder(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Suppression des chapitres',
            'short_title' => 'sdc',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1sdc']);
        $target = Version::factory()->for($work)->create(['folder' => '2sdc']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1sdc-2sdc-run1',
            'created_by' => $user->id,
        ]);

        Chapter::create([
            'folder' => $comparison->folder,
            'level' => '1',
            'label_source' => 'Première partie',
            'label_target' => 'Première partie',
            'chapter_parent' => 0,
            'start_line_source' => '1a',
            'start_line_target' => '1',
            'id_tome_source' => 0,
            'id_tome_target' => 0,
        ]);

        $response = $this->deleteJson("/comparisons/{$comparison->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Comparison deleted');

        $this->assertDatabaseMissing('comparisons', ['id' => $comparison->id]);
        $this->assertSame(0, Chapter::query()->forFolder($comparison->folder)->count());
    }

    public function test_deleting_comparison_removes_medite_inputs_and_prunes_empty_comparison_dirs(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [
            'name' => 'Auteur test cleanup',
            'folder' => 'cleanup_author',
        ], [
            'title' => 'Oeuvre cleanup',
            'short_title' => 'cln',
            'folder' => 'cleanup_work',
        ]);

        $source = Version::factory()->for($work)->create(['folder' => '1cln']);
        $target = Version::factory()->for($work)->create(['folder' => '2cln']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cln-2cln-run1',
            'created_by' => $user->id,
        ]);

        $publicComparisonDir = storage_path("app/public/uploads/cleanup_author/cleanup_work/comparisons/{$comparison->id}");
        $legacyComparisonDir = base_path("../variance/uploads/cleanup_author/cleanup_work/comparisons/{$comparison->id}");
        $publicMediteDir = storage_path("app/public/uploads/__medite_inputs/{$comparison->id}");
        $legacyMediteDir = base_path("../variance/uploads/__medite_inputs/{$comparison->id}");

        File::ensureDirectoryExists($publicComparisonDir);
        File::put($publicComparisonDir . '/source.xhtml', '<div>ok</div>');
        File::ensureDirectoryExists($legacyComparisonDir);
        File::put($legacyComparisonDir . '/source.xhtml', '<div>ok</div>');
        File::ensureDirectoryExists($publicMediteDir);
        File::put($publicMediteDir . '/1cln.xml', '<xml/>');
        File::ensureDirectoryExists($legacyMediteDir);
        File::put($legacyMediteDir . '/1cln.xml', '<xml/>');

        $this->deleteJson("/comparisons/{$comparison->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comparison deleted');

        $this->assertDirectoryDoesNotExist($publicComparisonDir);
        $this->assertDirectoryDoesNotExist($legacyComparisonDir);
        $this->assertDirectoryDoesNotExist($publicMediteDir);
        $this->assertDirectoryDoesNotExist($legacyMediteDir);
        $this->assertDirectoryDoesNotExist(storage_path('app/public/uploads/cleanup_author/cleanup_work/comparisons'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/uploads/cleanup_author/cleanup_work'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/uploads/cleanup_author'));
        $this->assertDirectoryDoesNotExist(base_path('../variance/uploads/cleanup_author/cleanup_work/comparisons'));
        $this->assertDirectoryDoesNotExist(base_path('../variance/uploads/cleanup_author/cleanup_work'));
        $this->assertDirectoryDoesNotExist(base_path('../variance/uploads/cleanup_author'));
    }
}
