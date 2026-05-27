<?php

namespace Tests\Feature\Workflow;

use App\Models\Version;
use App\Models\Comparison;
use App\Services\PageMarkerService;
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

    public function test_reader_rebuild_endpoint_requires_authenticated_session(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-rebuild-auth-v1',
        ]);

        auth()->logout();

        $this->postJson("/api/versions/{$version->id}/reader/rebuild")
            ->assertStatus(401);
    }

    public function test_reader_rebuild_endpoint_clears_stale_artifacts_and_returns_fresh_payload(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-rebuild-v1',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            "Texte reconstruit."
        );
        $this->writeFacsimilePair($version);

        $artifactDir = storage_path("app/private/reader_cache/{$version->id}");
        File::ensureDirectoryExists($artifactDir);
        $artifactPath = $artifactDir . '/' . md5('AUTO') . '-' . md5('AUTO') . '.json';
        File::put($artifactPath, '{"stale":true}');

        $this->postJson("/api/versions/{$version->id}/reader/rebuild")
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version_id', $version->id)
            ->assertJsonPath('page_count', 1)
            ->assertJsonPath('current_page.text', 'Texte reconstruit.');

        $this->assertFileExists($artifactPath);
        $artifact = json_decode((string) File::get($artifactPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($artifact['dataset'] ?? null);
        $this->assertSame('Texte reconstruit.', $artifact['dataset']['text'] ?? null);
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
            ->assertJsonPath('pages.0.image.name', 'img_reader-fulltext-v1_001.jpg')
            ->assertJsonPath('pages.0.imageCode', '001')
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

    public function test_reader_aligns_folio_markers_to_trailing_facsimiles_when_images_start_before_first_page(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $source = Version::factory()->for($work)->create([
            'folder' => 'reader-folio-v1',
            'name' => 'Lettre d’un fou (1885)',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => 'reader-folio-v2',
            'name' => 'Version cible',
        ]);

        foreach (['001', '002', '003', '004', '005'] as $basename) {
            $this->writeFacsimilePair($source, $basename);
        }

        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => <<<HTML
<div>
  <pb n="1a"/>
  <p>Mon cher docteur, je me mets entre vos mains.</p>
  <pb n="1b"/>
  <p>Suite du texte reconstitué.</p>
  <pb n="2a"/>
  <p>Fin du texte reconstitué.</p>
</div>
HTML,
        ]);

        $this->getJson("/api/versions/{$source->id}/reader?text_source=comparison-xhtml")
            ->assertOk()
            ->assertJsonPath('page_count', 3)
            ->assertJsonPath('pages.0.label', '1a')
            ->assertJsonPath('pages.0.image.name', 'img_reader-folio-v1_003.jpg')
            ->assertJsonPath('pages.1.image.name', 'img_reader-folio-v1_004.jpg')
            ->assertJsonPath('pages.2.image.name', 'img_reader-folio-v1_005.jpg')
            ->assertJsonPath('current_page.image.name', 'img_reader-folio-v1_003.jpg');
    }

    public function test_reader_aligns_numeric_markers_to_trailing_facsimiles_when_images_start_before_first_page(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $source = Version::factory()->for($work)->create([
            'folder' => 'reader-numeric-v1',
            'name' => 'Mercure Galant',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => 'reader-numeric-v2',
            'name' => 'Version cible',
        ]);

        foreach (['001', '002', '003', '004'] as $basename) {
            $this->writeFacsimilePair($source, $basename);
        }

        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => <<<HTML
<div>
  <pb n="75"/>
  <p>LA BELLE AU BOIS DORMANT.</p>
  <pb n="76"/>
  <p>CONTE.</p>
  <pb n="77"/>
  <p>Suite du texte reconstitué.</p>
</div>
HTML,
        ]);

        $this->getJson("/api/versions/{$source->id}/reader?text_source=comparison-xhtml")
            ->assertOk()
            ->assertJsonPath('page_count', 3)
            ->assertJsonPath('pages.0.label', '75')
            ->assertJsonPath('pages.0.image.name', 'img_reader-numeric-v1_002.jpg')
            ->assertJsonPath('pages.1.image.name', 'img_reader-numeric-v1_003.jpg')
            ->assertJsonPath('pages.2.image.name', 'img_reader-numeric-v1_004.jpg')
            ->assertJsonPath('current_page.image.name', 'img_reader-numeric-v1_002.jpg');
    }

    public function test_reader_maps_legacy_xhtml_page_marker_spans_to_facsimiles(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $source = Version::factory()->for($work)->create([
            'folder' => 'reader-legacy-span-v1',
            'name' => 'Albert Savarus (Le Siècle, 1842)',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => 'reader-legacy-span-v2',
            'name' => 'Version cible',
        ]);

        foreach (['001', '002', '003'] as $basename) {
            $this->writeFacsimilePair($source, $basename);
        }

        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => <<<'HTML'
<div>
  <span class="page-marker" data-image-name="002"><span class="page-number">1.<br />1a</span><img src="/img/settings/page_right.svg" /></span>
  <span>Premier extrait.</span>
  <span class="page-marker" data-image-name="003"><span class="page-number">1.<br />1b</span><img src="/img/settings/page_right.svg" /></span>
  <span>Second extrait.</span>
</div>
HTML,
        ]);

        $this->getJson("/api/versions/{$source->id}/reader?text_source=comparison-xhtml")
            ->assertOk()
            ->assertJsonPath('page_count', 3)
            ->assertJsonPath('pages.0.label', 'Avant 1.1a')
            ->assertJsonPath('pages.0.image.name', 'img_reader-legacy-span-v1_001.jpg')
            ->assertJsonPath('pages.1.label', '1.1a')
            ->assertJsonPath('pages.1.image.name', 'img_reader-legacy-span-v1_002.jpg')
            ->assertJsonPath('pages.2.label', '1.1b')
            ->assertJsonPath('pages.2.image.name', 'img_reader-legacy-span-v1_003.jpg')
            ->assertJsonPath('current_page.image.name', 'img_reader-legacy-span-v1_001.jpg');
    }

    public function test_reader_prefers_explicit_pagination_image_codes_over_trailing_alignment(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-explicit-sidecar-v1',
            'name' => 'Le Bien Public',
        ]);

        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            "FEUILLETON\n\nTexte initial.\n\nSuite.\n\nFin."
        );

        foreach (range(1, 15) as $index) {
            $this->writeFacsimilePair($version, str_pad((string) $index, 3, '0', STR_PAD_LEFT));
        }

        File::put(
            storage_path("app/private/pagination/{$version->id}.json"),
            json_encode([
                'origin' => 'lignes',
                'marker_count' => 3,
                'missed_count' => 0,
                'markers' => [
                    [
                        'char_index' => 12,
                        'resolved_char_index' => 12,
                        'image' => '2',
                        'image_code' => '002',
                        'page' => '1a',
                        'phrase' => 'Texte initial.',
                        'line' => 2,
                    ],
                    [
                        'char_index' => 28,
                        'resolved_char_index' => 28,
                        'image' => '3',
                        'image_code' => '003',
                        'page' => '1b',
                        'phrase' => 'Suite.',
                        'line' => 3,
                    ],
                    [
                        'char_index' => 36,
                        'resolved_char_index' => 36,
                        'image' => '4',
                        'image_code' => '004',
                        'page' => '1c',
                        'phrase' => 'Fin.',
                        'line' => 4,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->getJson("/api/versions/{$version->id}/reader")
            ->assertOk()
            ->assertJsonPath('pagination.available', true)
            ->assertJsonPath('pages.0.label', 'Avant 1a')
            ->assertJsonPath('pages.0.image.name', 'img_reader-explicit-sidecar-v1_001.jpg')
            ->assertJsonPath('pages.1.label', '1a')
            ->assertJsonPath('pages.1.image.name', 'img_reader-explicit-sidecar-v1_002.jpg')
            ->assertJsonPath('pages.2.label', '1b')
            ->assertJsonPath('pages.2.image.name', 'img_reader-explicit-sidecar-v1_003.jpg')
            ->assertJsonPath('pages.3.label', '1c')
            ->assertJsonPath('pages.3.image.name', 'img_reader-explicit-sidecar-v1_004.jpg');
    }

    public function test_short_one_line_lignes_file_generates_pagination_sidecar(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-short-lignes-v1',
        ]);

        $this->writeVersionXml(
            $version,
            '<p>Mon cher docteur, je me mets entre vos mains.</p><p>Notre machine nous aurait fait une autre intelligence.</p>'
        );

        $lignesPath = storage_path('app/private/short-one-line-lignes.txt');
        File::put(
            $lignesPath,
            "0002\t1a\tMon cher docteur, je me mets entre vos\n"
            . "0003\t1b\tNotre machine nous aurait fait une\n"
        );

        $result = app(PageMarkerService::class)->generatePaginationSidecar($version, $lignesPath);

        $this->assertSame(2, $result['payload']['marker_count']);
        $this->assertSame('lignes', $result['payload']['origin']);
        $this->assertSame('002', $result['payload']['markers'][0]['image_code']);
        $this->assertSame('003', $result['payload']['markers'][1]['image_code']);
    }

    public function test_reader_handles_multibyte_text_when_refining_sidecar_markers(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'reader-multibyte-v1',
        ]);

        $text = str_repeat('€', 100) . 'FIN';
        File::put(
            storage_path("app/public/uploads/versions/{$version->folder}.txt"),
            $text
        );

        $this->writeFacsimilePair($version, '001');
        $this->writeFacsimilePair($version, '002');

        File::put(
            storage_path("app/private/pagination/{$version->id}.json"),
            json_encode([
                'version_id' => $version->id,
                'version_folder' => $version->folder,
                'work_id' => $version->work_id,
                'origin' => 'lignes',
                'marker_count' => 1,
                'missed_count' => 0,
                'markers' => [
                    [
                        'char_index' => 0,
                        'image' => '2',
                        'image_code' => '002',
                        'page' => '2',
                        'phrase' => 'FIN',
                        'line' => 1,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->getJson("/api/versions/{$version->id}/reader")
            ->assertOk()
            ->assertJsonPath('pagination.available', true)
            ->assertJsonPath('pages.0.label', '2')
            ->assertJsonPath('pages.0.image.name', 'img_reader-multibyte-v1_002.jpg');
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
