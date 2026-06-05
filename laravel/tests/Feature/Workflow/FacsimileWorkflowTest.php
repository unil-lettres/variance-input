<?php

namespace Tests\Feature\Workflow;

use App\Jobs\ProcessFacsimileImage;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FacsimileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_facsimiles_queues_images_for_processing(): void
    {
        Queue::fake();

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Facsimilés de travail',
            'short_title' => 'fdt',
        ]);
        $version = Version::factory()->for($work)->create([
            'name' => 'Version source',
            'folder' => '1fdt',
        ]);

        $response = $this->postJson('/api/upload_facsimiles', [
            'version_id' => $version->id,
            'images' => [
                UploadedFile::fake()->image('002.png', 20, 20),
                UploadedFile::fake()->image('001.jpg', 20, 20),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('files_added', 2)
            ->assertJsonPath('processing', true);

        Queue::assertPushed(ProcessFacsimileImage::class, 2);
    }

    public function test_upload_facsimiles_rejects_legacy_versions(): void
    {
        Queue::fake();

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Facsimilés legacy',
            'short_title' => 'fdl',
            'is_legacy' => true,
        ]);
        $version = Version::factory()->for($work)->create([
            'name' => 'Version legacy',
            'folder' => '1fdl',
            'is_legacy' => true,
        ]);

        $response = $this->postJson('/api/upload_facsimiles', [
            'version_id' => $version->id,
            'images' => [
                UploadedFile::fake()->image('001.jpg', 20, 20),
            ],
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error', 'Les versions legacy sont en lecture seule.');

        Queue::assertNothingPushed();
    }

    public function test_upload_facsimiles_requires_work_edit_permission(): void
    {
        Queue::fake();

        $owner = $this->signInEditor();
        $work = $this->createEditableWork($owner, [], [
            'title' => 'Facsimilés protégés',
            'short_title' => 'fp',
        ]);
        $version = Version::factory()->for($work)->create([
            'name' => 'Version protégée',
            'folder' => '1fp',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'other-facsimile-editor',
            'full_name' => 'Other Facsimile Editor',
            'is_admin' => false,
        ]);
        $this->actingAs($otherUser);

        $response = $this->postJson('/api/upload_facsimiles', [
            'version_id' => $version->id,
            'images' => [
                UploadedFile::fake()->image('001.jpg', 20, 20),
            ],
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error', 'Vous n’avez pas la permission de modifier les facsimilés de cette œuvre.');

        Queue::assertNothingPushed();
    }

    public function test_cancel_facsimile_upload_requires_work_edit_permission(): void
    {
        $owner = $this->signInEditor();
        $work = $this->createEditableWork($owner, [], [
            'title' => 'Annulation protégée',
            'short_title' => 'ap',
        ]);
        $version = Version::factory()->for($work)->create([
            'name' => 'Version protégée',
            'folder' => '1ap',
        ]);

        $version->loadMissing('work.author');
        $dir = storage_path(sprintf(
            'app/public/uploads/%s/%s/%s',
            $version->work->author->folder,
            $version->work->folder,
            $version->folder
        ));
        File::ensureDirectoryExists($dir);
        File::put($dir . '/img_1ap_001.jpg', 'protected');

        $otherUser = User::factory()->create([
            'name' => 'other-facsimile-cancel',
            'full_name' => 'Other Facsimile Cancel',
            'is_admin' => false,
        ]);
        $this->actingAs($otherUser);

        $response = $this->deleteJson("/api/versions/{$version->id}/facsimiles/cancel-upload");

        $response->assertForbidden()
            ->assertJsonPath('error', 'Vous n’avez pas la permission de modifier les facsimilés de cette œuvre.');

        $this->assertFileExists($dir . '/img_1ap_001.jpg');
    }

    public function test_facsimile_space_check_uses_public_uploads_path(): void
    {
        $this->signInEditor();

        $response = $this->getJson('/api/facsimiles/space?required_bytes=1024');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('path', public_path('uploads'))
            ->assertJsonPath('required_bytes', 1024);
    }
}
