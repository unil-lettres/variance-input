<?php

namespace Tests\Feature\Workflow;

use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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

        $this->assertStringContainsString('Bonjour  monde', $xml);
        $this->assertStringContainsString('Deux  espaces', $xml);
        $this->assertStringNotContainsString('<lb/>', $xml);
        $this->assertStringContainsString("Bonjour  monde\nDeux  espaces", $xml);
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
}
