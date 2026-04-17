<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VersionImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_import_applies_default_normalizations_to_generated_tei(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Les signes parmi nous',
            'short_title' => 'lspn',
        ]);

        $sourceText = "  Bonjour\u{00A0}\u{202F}monde  \r\n\tDeux  espaces\t\r\n\r\n";
        $upload = UploadedFile::fake()->createWithContent('version.txt', $sourceText);

        $response = $this->post('/api/versions', [
            'work_id' => $work->id,
            'name' => 'Édition témoin',
            'versionFile' => $upload,
        ]);

        $response->assertCreated()
            ->assertJsonPath('version.name', 'Édition témoin')
            ->assertJsonPath('version.work_id', $work->id);

        $versionId = (int) $response->json('version.id');
        $version = Version::findOrFail($versionId);

        $txtPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        $xmlPath = storage_path("app/public/uploads/versions/{$version->folder}.xml");

        $this->assertFileExists($txtPath);
        $this->assertFileExists($xmlPath);
        $this->assertSame($sourceText, File::get($txtPath));

        $xml = File::get($xmlPath);

        $this->assertStringContainsString('Bonjour monde', $xml);
        $this->assertStringContainsString('Deux espaces', $xml);
        $this->assertStringNotContainsString('<lb/>', $xml);
        $this->assertStringNotContainsString("\u{00A0}", $xml);
        $this->assertStringNotContainsString("\u{202F}", $xml);
        $this->assertStringNotContainsString("\t", $xml);
    }

    public function test_versions_index_ignores_stale_page_marker_progress_for_legacy_versions(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Les Cahiers vaudois',
            'short_title' => 'lcv',
        ]);
        $version = Version::factory()->for($work)->create([
            'name' => 'Grasset (1931)',
            'folder' => 'grasset1931',
            'is_legacy' => true,
        ]);

        File::ensureDirectoryExists(storage_path('app/tmp/pager'));
        File::put(
            storage_path("app/tmp/pager/{$version->id}.json"),
            json_encode([
                'status' => 'done',
                'updated_at' => time(),
                'markers' => 71,
            ], JSON_THROW_ON_ERROR)
        );

        $response = $this->getJson("/api/versions?work_id={$work->id}&fresh=1");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $version->id)
            ->assertJsonPath('0.is_legacy', true)
            ->assertJsonPath('0.page_marker_progress', null);
    }

    public function test_pagination_done_toggle_records_the_validating_user(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Oeuvre de pagination',
            'short_title' => 'odp',
        ]);
        $version = Version::factory()->for($work)->create([
            'name' => 'Version A',
            'folder' => '1odp',
        ]);

        $response = $this->patchJson("/api/versions/{$version->id}/pagination/done", [
            'done' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('pagination_done', true)
            ->assertJsonPath('pagination_done_by', $user->id)
            ->assertJsonPath('pagination_done_by_name', $user->name);

        $version->refresh();

        $this->assertTrue($version->pagination_done);
        $this->assertSame($user->id, $version->pagination_done_by);
        $this->assertNotNull($version->pagination_done_at);
    }

    public function test_versions_index_exposes_comparison_usage_for_versions_in_use(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'La Cousine Bette',
            'short_title' => 'lcb',
        ]);

        $source = Version::factory()->for($work)->create([
            'name' => 'Le Musée littéraire du Siècle (1847)',
            'folder' => '5lcb',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Furne (1848)',
            'folder' => '6lcb',
        ]);

        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '5lcb-6lcb-run1',
            'created_by' => $user->id,
        ]);

        $response = $this->getJson("/api/versions?work_id={$work->id}&fresh=1");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $source->id,
                'is_in_use' => true,
                'in_use_comparison_count' => 1,
            ])
            ->assertJsonFragment([
                'id' => $target->id,
                'is_in_use' => true,
                'in_use_comparison_count' => 1,
            ]);

        $versions = collect($response->json());

        $this->assertSame(
            [$comparison->id],
            $versions->firstWhere('id', $source->id)['in_use_comparison_ids'] ?? null
        );
        $this->assertSame(
            [$comparison->id],
            $versions->firstWhere('id', $target->id)['in_use_comparison_ids'] ?? null
        );
    }

    public function test_deleting_version_removes_private_artifacts_and_prunes_empty_facsimile_dirs(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [
            'name' => 'Auteur version cleanup',
            'folder' => 'cleanup_version_author',
        ], [
            'title' => 'Oeuvre version cleanup',
            'short_title' => 'cvu',
            'folder' => 'cleanup_version_work',
        ]);

        $version = Version::factory()->for($work)->create([
            'folder' => '1cvu',
            'name' => 'Version cleanup',
        ]);

        $this->writeFacsimilePair($version, '001');

        $queueDir = storage_path('app/private/facsimile_queue/cleanup_version_author/cleanup_version_work/1cvu');
        $backupDir = storage_path('app/private/facsimile_backups/' . $version->id);
        $cancelFlag = storage_path('app/private/facsimile_cancel/' . $version->id . '.flag');
        File::ensureDirectoryExists($queueDir);
        File::put($queueDir . '/queued.txt', 'queued');
        File::ensureDirectoryExists($backupDir);
        File::put($backupDir . '/meta.json', '{}');
        File::ensureDirectoryExists(dirname($cancelFlag));
        File::put($cancelFlag, '1');

        Storage::disk('local')->put("lignes/{$version->id}.txt", "001\n1\nTexte");
        Storage::disk('local')->put("pagination/{$version->id}.json", json_encode(['markers' => []]));

        $response = $this->deleteJson("/api/versions/{$version->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('versions', ['id' => $version->id]);
        $this->assertDirectoryDoesNotExist(storage_path('app/public/uploads/cleanup_version_author/cleanup_version_work/1cvu'));
        $this->assertDirectoryDoesNotExist(base_path('../variance/uploads/cleanup_version_author/cleanup_version_work/1cvu'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/uploads/cleanup_version_author/cleanup_version_work'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/uploads/cleanup_version_author'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/facsimile_queue/cleanup_version_author/cleanup_version_work/1cvu'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/facsimile_queue/cleanup_version_author/cleanup_version_work'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/facsimile_queue/cleanup_version_author'));
        $this->assertDirectoryDoesNotExist($backupDir);
        $this->assertFileDoesNotExist($cancelFlag);
        $this->assertFalse(Storage::disk('local')->exists("lignes/{$version->id}.txt"));
        $this->assertFalse(Storage::disk('local')->exists("pagination/{$version->id}.json"));
    }
}
