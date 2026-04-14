<?php

namespace Tests\Feature\Workflow;

use App\Models\Version;
use App\Models\Comparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VersionReaderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_endpoints_require_authenticated_session(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-auth-v1',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            "Texte d'acces"
        );
        $this->writeFacsimilePair($version);

        auth()->logout();

        $this->getJson("/api/versions/{$version->id}/reader")
            ->assertStatus(401);

        $this->getJson("/api/versions/{$version->id}/reader/progress")
            ->assertStatus(401);

        $this->getJson("/api/versions/{$version->id}/reader/page?index=0")
            ->assertStatus(401);
    }

    public function test_reader_endpoints_return_payload_for_authenticated_editor(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-data-v1',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            "Bonjour le monde."
        );
        $this->writeFacsimilePair($version);

        $this->getJson("/api/versions/{$version->id}/reader")
            ->assertOk()
            ->assertJsonPath('version_id', $version->id)
            ->assertJsonPath('text_available', true)
            ->assertJsonPath('text_source', 'version-txt')
            ->assertJsonPath('page_count', 1)
            ->assertJsonPath('current_page.text', 'Bonjour le monde.');

        $this->getJson("/api/versions/{$version->id}/reader/page?index=0")
            ->assertOk()
            ->assertJsonPath('version_id', $version->id)
            ->assertJsonPath('page_index', 0)
            ->assertJsonPath('page.text', 'Bonjour le monde.');
    }

    public function test_reader_progress_endpoint_returns_idle_without_active_load(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-progress-v1',
        ]);

        $this->getJson("/api/versions/{$version->id}/reader/progress")
            ->assertOk()
            ->assertJsonPath('version_id', $version->id)
            ->assertJsonPath('status', 'idle')
            ->assertJsonPath('percent', 0);
    }

    public function test_reader_without_pagination_returns_full_text_in_single_page(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-fulltext-v1',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            "Premier paragraphe.\n\nSecond paragraphe."
        );
        $this->writeFacsimilePair($version, '001');
        $this->writeFacsimilePair($version, '002');

        $this->getJson("/api/versions/{$version->id}/reader")
            ->assertOk()
            ->assertJsonPath('page_count', 1)
            ->assertJsonPath('pages.0.label', 'Texte complet')
            ->assertJsonPath('pagination.available', false)
            ->assertJsonPath('current_page.text', "Premier paragraphe.\n\nSecond paragraphe.");
    }

    public function test_reader_allows_switching_between_version_text_and_xhtml_reconstruction(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $source = Version::factory()->for($work)->create([
            'folder' => 'reader-source-v1',
            'name' => 'Version source',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => 'reader-source-v2',
            'name' => 'Version cible',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$source->folder}.txt"),
            'Texte version'
        );

        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'created_by' => $user->id,
        ]);
        $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<div><p>Texte reconstruit XHTML</p></div>',
        ]);

        $this->getJson("/api/versions/{$source->id}/reader")
            ->assertOk()
            ->assertJsonPath('text_source', 'version-txt')
            ->assertJsonPath('text_source_options.0.value', 'version-txt')
            ->assertJsonFragment(['value' => 'comparison-xhtml'])
            ->assertJsonPath('current_page.text', 'Texte version');

        $this->getJson("/api/versions/{$source->id}/reader?text_source=comparison-xhtml")
            ->assertOk()
            ->assertJsonPath('text_source', 'comparison-xhtml')
            ->assertJsonPath('current_page.text', 'Texte reconstruit XHTML');
    }

    public function test_default_reader_request_warms_explicit_xhtml_reader_artifact(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $source = Version::factory()->for($work)->create([
            'folder' => 'reader-warm-v1',
            'name' => 'Version source',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => 'reader-warm-v2',
            'name' => 'Version cible',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$source->folder}.txt"),
            'Texte version'
        );

        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'created_by' => $user->id,
        ]);
        $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<div><p>Texte reconstruit XHTML</p></div>',
        ]);

        $this->getJson("/api/versions/{$source->id}/reader")
            ->assertOk()
            ->assertJsonPath('text_source', 'version-txt');

        $artifactPath = storage_path(
            'app/private/reader_cache/' . $source->id . '/' . md5('AUTO') . '-' . md5('comparison-xhtml') . '.json'
        );

        $this->assertFileExists($artifactPath);

        $artifact = json_decode((string) File::get($artifactPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('comparison-xhtml', $artifact['text_source'] ?? null);
        $this->assertSame('comparison-xhtml', $artifact['dataset']['text_source'] ?? null);
        $this->assertSame('Texte reconstruit XHTML', $artifact['dataset']['text'] ?? null);
    }

    public function test_convert_text_to_utf8_requires_authenticated_session(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-convert-auth-v1',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            mb_convert_encoding('Cafe deja', 'Windows-1252', 'UTF-8')
        );

        auth()->logout();

        $this->postJson("/api/versions/{$version->id}/text/convert-utf8", [
            'encoding' => 'Windows-1252',
        ])->assertStatus(401);
    }

    public function test_convert_text_to_utf8_rejects_legacy_versions(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-convert-legacy-v1',
            'is_legacy' => true,
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            mb_convert_encoding('Cafe deja', 'Windows-1252', 'UTF-8')
        );

        $this->postJson("/api/versions/{$version->id}/text/convert-utf8", [
            'encoding' => 'Windows-1252',
        ])->assertForbidden();
    }

    public function test_convert_text_to_utf8_rewrites_text_and_clears_reader_artifacts(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-convert-v1',
        ]);

        $txtPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        File::put($txtPath, mb_convert_encoding('Café déjà.', 'Windows-1252', 'UTF-8'));

        $artifactDir = storage_path("app/private/reader_cache/{$version->id}");
        File::ensureDirectoryExists($artifactDir);
        File::put($artifactDir . '/stale.json', '{"stale":true}');

        $this->postJson("/api/versions/{$version->id}/text/convert-utf8", [
            'encoding' => 'Windows-1252',
        ])->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('encoding', 'UTF-8');

        $this->assertSame('Café déjà.', File::get($txtPath));
        $this->assertDirectoryDoesNotExist($artifactDir);
    }

    public function test_merge_from_pb_clears_existing_reader_artifacts(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-merge-v1',
        ]);

        $this->writeVersionXml($version, '<p>Avant<pb n="1" facs="img_reader-merge-v1_001.jpg"/>Après</p>');

        $artifactDir = storage_path("app/private/reader_cache/{$version->id}");
        File::ensureDirectoryExists($artifactDir);
        File::put($artifactDir . '/stale.json', '{"stale":true}');

        $this->postJson("/api/versions/{$version->id}/pagination/merge-from-pb")
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDirectoryDoesNotExist($artifactDir);
    }
}
