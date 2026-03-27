<?php

namespace Tests\Feature\Workflow;

use App\Models\Author;
use App\Models\Permission;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_a_new_work_under_a_legacy_author(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Charles Ferdinand Ramuz',
            'is_legacy' => true,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        $response = $this->postJson('/api/works', [
            'author_id' => $author->id,
            'title' => 'Nouvelle œuvre critique',
            'short_title' => 'noc',
        ]);

        $response->assertCreated()
            ->assertJsonPath('author_id', $author->id)
            ->assertJsonPath('title', 'Nouvelle œuvre critique')
            ->assertJsonPath('short_title', 'noc');

        $work = Work::query()->where('author_id', $author->id)->firstOrFail();

        $this->assertFalse((bool) $work->is_legacy);
        $this->assertDatabaseHas('works_status', [
            'work_id' => $work->id,
        ]);
        $this->assertDatabaseHas('permissions', [
            'user_id' => $user->id,
            'work_id' => $work->id,
            'permission_type' => 'edit',
        ]);
    }

    public function test_can_edit_endpoint_distinguishes_editable_and_legacy_works(): void
    {
        $user = $this->signInEditor();
        $editable = $this->createEditableWork($user);
        $legacy = Work::factory()->for($editable->author)->create([
            'title' => 'Œuvre legacy',
            'short_title' => 'leg',
            'is_legacy' => true,
        ]);

        $this->getJson("/works/{$editable->id}/can-edit")
            ->assertOk()
            ->assertJson(['canEdit' => true]);

        $this->getJson("/works/{$legacy->id}/can-edit")
            ->assertOk()
            ->assertJson(['canEdit' => false]);
    }

    public function test_legacy_work_rejects_description_updates(): void
    {
        $this->signInEditor();
        $legacy = Work::factory()->for(Author::factory())->create([
            'title' => 'Albert Savarus',
            'short_title' => 'sav',
            'is_legacy' => true,
        ]);

        $response = $this->postJson("/works/{$legacy->id}/description", [
            'desc' => 'Texte critique',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error', 'Les œuvres legacy sont en lecture seule.');
    }

    public function test_editor_can_update_description_on_editable_work(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);

        $response = $this->postJson("/works/{$work->id}/description", [
            'desc' => 'Présentation publique révisée.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('works', [
            'id' => $work->id,
            'desc' => 'Présentation publique révisée.',
        ]);
    }

    public function test_work_short_title_cannot_be_changed_once_created(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Le Parfum des îles Borromées',
            'short_title' => 'pib',
        ]);

        $response = $this->putJson("/api/works/{$work->id}", [
            'title' => 'Le Parfum des îles Borromées, édition revue',
            'short_title' => 'pibr',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath(
                'error',
                'Le titre abrégé ne peut pas être modifié car il est utilisé pour les dossiers et fichiers liés à l\'œuvre.'
            );

        $this->assertDatabaseHas('works', [
            'id' => $work->id,
            'title' => 'Le Parfum des îles Borromées',
            'short_title' => 'pib',
        ]);
    }
}
