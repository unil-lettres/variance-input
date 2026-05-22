<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Permission;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestrictedVersionEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_and_revoke_version_editor_permission(): void
    {
        $this->signInAdmin();
        $user = User::factory()->create(['is_admin' => false]);
        $work = $this->createEditableWork($this->signInAdmin());

        $this->post("/users/{$user->id}/version-editor-permissions", [
            'work_id' => $work->id,
        ])->assertRedirect(admin_path('users'));

        $permission = Permission::query()
            ->where('user_id', $user->id)
            ->where('work_id', $work->id)
            ->where('permission_type', User::PERMISSION_VERSION_EDITOR)
            ->first();

        $this->assertNotNull($permission);

        $this->delete("/users/{$user->id}/version-editor-permissions/{$permission->id}")
            ->assertRedirect(admin_path('users'));

        $this->assertDatabaseMissing('permissions', [
            'id' => $permission->id,
        ]);
    }

    public function test_admin_can_grant_and_revoke_work_edit_permission(): void
    {
        $this->signInAdmin();
        $user = User::factory()->create(['is_admin' => false]);
        $work = $this->createEditableWork($this->signInAdmin());

        $this->post("/users/{$user->id}/work-edit-permissions", [
            'work_id' => $work->id,
        ])->assertRedirect(admin_path('users'));

        $permission = Permission::query()
            ->where('user_id', $user->id)
            ->where('work_id', $work->id)
            ->where('permission_type', User::PERMISSION_EDIT)
            ->first();

        $this->assertNotNull($permission);

        $this->delete("/users/{$user->id}/work-edit-permissions/{$permission->id}")
            ->assertRedirect(admin_path('users'));

        $this->assertDatabaseMissing('permissions', [
            'id' => $permission->id,
        ]);
    }

    public function test_admin_targets_do_not_receive_redundant_version_editor_permission(): void
    {
        $this->signInAdmin();
        $admin = User::factory()->create(['is_admin' => true]);
        $work = $this->createEditableWork($this->signInAdmin());

        $this->post("/users/{$admin->id}/version-editor-permissions", [
            'work_id' => $work->id,
        ])->assertRedirect(admin_path('users'));

        $this->assertDatabaseMissing('permissions', [
            'user_id' => $admin->id,
            'work_id' => $work->id,
            'permission_type' => User::PERMISSION_VERSION_EDITOR,
        ]);
    }

    public function test_users_page_hides_version_editor_assignment_controls_for_admins(): void
    {
        $this->signInAdmin();
        User::factory()->create([
            'full_name' => 'Full Access Admin',
            'is_admin' => true,
        ]);
        User::factory()->create([
            'full_name' => 'Restricted Candidate',
            'is_admin' => false,
        ]);

        $response = $this->get('/users')->assertOk();

        $response->assertSee('Accès complet.');
        $response->assertSee('Ajouter une œuvre…');
        $this->assertSame(2, substr_count($response->getContent(), 'Ajouter une œuvre…'));
    }

    public function test_users_page_prints_full_edit_permissions_for_researchers(): void
    {
        $this->signInAdmin();
        $user = User::factory()->create([
            'full_name' => 'Work Editor',
            'is_admin' => false,
        ]);
        $work = $this->createEditableWork($user, ['name' => 'Auteur visible'], ['title' => 'Œuvre visible']);

        $this->get('/users')
            ->assertOk()
            ->assertSee('Droits édition complète')
            ->assertSee('Auteur visible')
            ->assertSee('Œuvre visible')
            ->assertSee('Aucun accès restreint assigné.');
    }

    public function test_restricted_version_editor_can_open_load_save_and_toggle_assigned_version(): void
    {
        $user = $this->signInEditor(User::factory()->create(['is_admin' => false]));
        $work = $this->createEditableWork($this->signInAdmin());
        $version = Version::factory()->for($work)->create(['folder' => 'rvev1']);
        $this->writeVersionXml($version, '<p>Assigned payload</p>');
        $this->grantWorkVersionEditorPermission($user, $work);
        $this->actingAs($user);

        $this->get(route('version.editor', $version))->assertOk();

        $this->get(route('version.editor.document', $version))
            ->assertOk()
            ->assertSee('Assigned payload', false);

        $newXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><div><p>Updated by restricted editor</p></div></body></text></TEI>
XML;

        $this->call(
            'PUT',
            route('version.editor.update', $version),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/xml'],
            $newXml
        )->assertOk();

        $this->assertStringContainsString('Updated by restricted editor', file_get_contents($version->getXMLFilePath()));

        $this->postJson("/versions/{$version->id}/facsimiles/toggle-ignored", [
            'filename' => 'page_001.jpg',
        ])->assertOk()
            ->assertJsonPath('ignored', true);
    }

    public function test_restricted_version_editor_is_denied_unassigned_versions_and_full_editor_actions(): void
    {
        $user = $this->signInEditor(User::factory()->create(['is_admin' => false]));
        $assignedWork = $this->createEditableWork($this->signInAdmin());
        $unassignedWork = $this->createEditableWork($this->signInAdmin());
        $assignedVersion = Version::factory()->for($assignedWork)->create(['folder' => 'rvev2']);
        $unassignedVersion = Version::factory()->for($unassignedWork)->create(['folder' => 'rvev3']);
        $this->writeVersionXml($assignedVersion, '<p>Assigned</p>');
        $this->writeVersionXml($unassignedVersion, '<p>Unassigned</p>');
        $this->grantWorkVersionEditorPermission($user, $assignedWork);
        $this->actingAs($user);

        $this->get(route('version.editor', $unassignedVersion))->assertForbidden();
        $this->get(route('version.editor.document', $unassignedVersion))->assertForbidden();

        $this->putJson("/api/versions/{$assignedVersion->id}", [
            'name' => 'Forbidden rename',
        ])->assertForbidden();

        $this->postJson("/works/{$assignedWork->id}/description", [
            'desc' => 'Forbidden description update',
        ])->assertForbidden();

        $comparison = Comparison::factory()->create([
            'source_id' => $assignedVersion->id,
            'target_id' => $assignedVersion->id,
            'created_by' => $user->id,
        ]);

        $this->get(route('comparison.editor', $comparison))->assertForbidden();
    }

    public function test_restricted_version_editor_cannot_create_authors_or_works_via_api(): void
    {
        $user = $this->signInEditor(User::factory()->create(['is_admin' => false]));
        $work = $this->createEditableWork($this->signInAdmin());
        $this->grantWorkVersionEditorPermission($user, $work);
        $this->actingAs($user);

        $this->postJson('/api/authors', [
            'name' => 'Auteur interdit',
        ])->assertForbidden()
            ->assertJsonPath('error', 'Accès limité à l’éditeur de versions.');

        $this->postJson('/api/works', [
            'author_id' => $work->author_id,
            'title' => 'Œuvre interdite',
            'short_title' => 'forbid',
        ])->assertForbidden()
            ->assertJsonPath('error', 'Accès limité à l’éditeur de versions.');
    }

    public function test_restricted_version_editor_home_lists_only_assigned_versions(): void
    {
        $user = $this->signInEditor(User::factory()->create(['is_admin' => false]));
        $assignedWork = $this->createEditableWork($this->signInAdmin(), [], ['title' => 'Assigned Work']);
        $unassignedWork = $this->createEditableWork($this->signInAdmin(), [], ['title' => 'Unassigned Work']);
        Version::factory()->for($assignedWork)->create(['name' => 'Assigned Version']);
        Version::factory()->for($unassignedWork)->create(['name' => 'Unassigned Version']);
        $this->grantWorkVersionEditorPermission($user, $assignedWork);
        $this->actingAs($user);

        $this->get('/')
            ->assertOk()
            ->assertSee('Assigned Work')
            ->assertSee('Assigned Version')
            ->assertSee('action="' . admin_path('logout') . '"', false)
            ->assertDontSee('Unassigned Work')
            ->assertDontSee('Unassigned Version');
    }
}
