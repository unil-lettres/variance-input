<?php

namespace Tests\Feature\Workflow;

use App\Models\Author;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAuthorizationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_update_requires_author_edit_permission(): void
    {
        $owner = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur protégé',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($owner, $author);

        $otherUser = User::factory()->create([
            'name' => 'other-author-editor',
            'full_name' => 'Other Author Editor',
            'is_admin' => false,
        ]);
        $this->actingAs($otherUser);

        $response = $this->putJson("/api/authors/{$author->id}", [
            'name' => 'Auteur modifié',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error', 'Vous n’avez pas la permission de modifier cet auteur.');

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'Auteur protégé',
        ]);
    }

    public function test_author_delete_requires_author_edit_permission(): void
    {
        $owner = $this->signInEditor();
        $author = Author::factory()->create([
            'name' => 'Auteur suppression protégée',
            'is_legacy' => false,
        ]);
        $this->grantAuthorEditPermission($owner, $author);

        $otherUser = User::factory()->create([
            'name' => 'other-author-delete',
            'full_name' => 'Other Author Delete',
            'is_admin' => false,
        ]);
        $this->actingAs($otherUser);

        $response = $this->deleteJson("/api/authors/{$author->id}");

        $response->assertForbidden()
            ->assertJsonPath('error', 'Vous n’avez pas la permission de modifier cet auteur.');

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'Auteur suppression protégée',
        ]);
    }
}
