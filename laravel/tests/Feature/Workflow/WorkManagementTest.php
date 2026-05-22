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
            ->assertJsonPath('short_title', 'noc')
            ->assertJsonPath('catalog_group', 'main');

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

    public function test_cannot_create_duplicate_author_name(): void
    {
        $user = $this->signInEditor();
        $existing = Author::factory()->create([
            'name' => 'Honoré de Balzac',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $existing);

        $response = $this->postJson('/api/authors', [
            'name' => 'Honoré de Balzac',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertSame(
            'Cet auteur existe déjà.',
            $response->json('errors.name.0')
        );
    }

    public function test_cannot_create_two_works_with_same_short_title(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur de test',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        Work::factory()->for($author)->create([
            'title' => 'Première œuvre',
            'short_title' => 'pda',
        ]);

        $response = $this->postJson('/api/works', [
            'author_id' => $author->id,
            'title' => 'Seconde œuvre',
            'short_title' => 'pda',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['short_title']);

        $this->assertSame(
            'Ce code abrégé est déjà utilisé par une autre œuvre.',
            $response->json('errors.short_title.0')
        );

        $this->assertSame(1, Work::query()
            ->where('short_title', 'pda')
            ->count());
    }

    public function test_same_short_title_is_rejected_even_for_different_authors(): void
    {
        $user = $this->signInEditor();
        $authorA = Author::factory()->create([
            'name' => 'Auteur A',
            'is_legacy' => false,
        ]);
        $authorB = Author::factory()->create([
            'name' => 'Auteur B',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $authorA);
        $this->grantAuthorEditPermission($user, $authorB);

        Work::factory()->for($authorA)->create([
            'title' => 'Œuvre A',
            'short_title' => 'pda',
        ]);

        $response = $this->postJson('/api/works', [
            'author_id' => $authorB->id,
            'title' => 'Œuvre B',
            'short_title' => 'pda',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['short_title']);
    }

    public function test_missing_short_title_is_generated_automatically(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur auto',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        $response = $this->postJson('/api/works', [
            'author_id' => $author->id,
            'title' => 'Rosalie',
            'short_title' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('short_title', 'rosalie');
    }

    public function test_work_can_be_created_in_allographic_catalog(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur catalogue',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        $response = $this->postJson('/api/works', [
            'author_id' => $author->id,
            'title' => 'Poèmes nègres',
            'short_title' => 'pn',
            'catalog_group' => 'allographic',
        ]);

        $response->assertCreated()
            ->assertJsonPath('catalog_group', 'allographic');

        $this->assertDatabaseHas('works', [
            'author_id' => $author->id,
            'title' => 'Poèmes nègres',
            'catalog_group' => 'allographic',
        ]);
    }

    public function test_missing_short_title_prefers_title_initials_when_available(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur initiales',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        $response = $this->postJson('/api/works', [
            'author_id' => $author->id,
            'title' => "Page d'amour",
            'short_title' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('short_title', 'pda');
    }

    public function test_short_title_suggestion_prefers_initials(): void
    {
        $this->signInEditor();

        $this->getJson('/api/works/short-title-suggestion?title=' . urlencode("Page d'amour"))
            ->assertOk()
            ->assertJsonPath('short_title', 'pda');
    }

    public function test_generated_short_title_stays_alpha_when_initials_are_already_taken(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur collision',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        Work::factory()->create([
            'title' => 'Œuvre existante',
            'short_title' => 'pda',
        ]);

        $response = $this->postJson('/api/works', [
            'author_id' => $author->id,
            'title' => "Page d'amour",
            'short_title' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('short_title', 'pdaa');
    }

    public function test_short_title_suggestion_is_unique_when_initials_are_taken(): void
    {
        $this->signInEditor();

        Work::factory()->create([
            'title' => 'Œuvre existante',
            'short_title' => 'pda',
        ]);

        $this->getJson('/api/works/short-title-suggestion?title=' . urlencode("Page d'amour"))
            ->assertOk()
            ->assertJsonPath('short_title', 'pdaa');
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

    public function test_work_catalog_group_can_be_updated_without_changing_short_title(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Poèmes nègres',
            'short_title' => 'pn',
        ]);

        $response = $this->putJson("/api/works/{$work->id}", [
            'title' => $work->title,
            'short_title' => $work->short_title,
            'catalog_group' => 'allographic',
        ]);

        $response->assertOk()
            ->assertJsonPath('catalog_group', 'allographic');

        $this->assertDatabaseHas('works', [
            'id' => $work->id,
            'catalog_group' => 'allographic',
        ]);
    }

    public function test_cannot_delete_work_while_versions_still_exist_and_error_lists_them(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Oeuvre bloquée',
            'short_title' => 'obl',
        ]);

        \App\Models\Version::factory()->for($work)->create(['name' => 'Version A']);
        \App\Models\Version::factory()->for($work)->create(['name' => 'Version B']);

        $response = $this->deleteJson("/api/works/{$work->id}");

        $response->assertStatus(400)
            ->assertJsonPath('blocking_versions_count', 2)
            ->assertJsonPath('blocking_versions.0', 'Version A')
            ->assertJsonPath('blocking_versions.1', 'Version B');

        $this->assertStringContainsString('2 version(s)', $response->json('error'));
        $this->assertStringContainsString('Version A', $response->json('error'));
    }

    public function test_cannot_delete_author_while_works_still_exist_and_error_lists_them(): void
    {
        $user = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur bloqué',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($user, $author);

        Work::factory()->for($author)->create(['title' => 'Oeuvre A', 'short_title' => 'oa']);
        Work::factory()->for($author)->create(['title' => 'Oeuvre B', 'short_title' => 'ob']);

        $response = $this->deleteJson("/api/authors/{$author->id}");

        $response->assertStatus(409)
            ->assertJsonPath('blocking_works_count', 2)
            ->assertJsonPath('blocking_works.0', 'Oeuvre A')
            ->assertJsonPath('blocking_works.1', 'Oeuvre B');

        $this->assertStringContainsString('2 œuvre(s)', $response->json('error'));
        $this->assertStringContainsString('Oeuvre A', $response->json('error'));
    }
}
