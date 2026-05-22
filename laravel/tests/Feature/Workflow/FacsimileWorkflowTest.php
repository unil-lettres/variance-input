<?php

namespace Tests\Feature\Workflow;

use App\Jobs\ProcessFacsimileImage;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
