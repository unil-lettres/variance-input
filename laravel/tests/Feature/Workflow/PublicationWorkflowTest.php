<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;
use App\Jobs\GenerateLegacyExportJob;
use App\Services\LegacyExportService;

class PublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_copies_components_facsimiles_and_manifests_for_comparison(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication critique',
            'short_title' => 'pbc',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pbc',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pbc',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pbc-2pbc-run1',
            'created_by' => $user->id,
        ]);

        $sourceDir = $this->writeComparisonArtifacts($comparison);
        $this->writeFacsimilePair($source);
        $this->writeFacsimilePair($target);

        $response = $this->postJson('/api/publish_xhtml', [
            'comparison_id' => $comparison->id,
            'destination' => 'prod',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('facsimiles.source.status', 'ok')
            ->assertJsonPath('facsimiles.target.status', 'ok');

        $comparison->refresh();
        $this->assertSame('prod', $comparison->publication_scope);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $destDir = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}");
        $legacyDestDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}");

        $this->assertFileExists($destDir . '/source.xhtml');
        $this->assertFileExists($destDir . '/target.xhtml');
        $this->assertStringNotContainsString('<div>', File::get($destDir . '/source.xhtml'));
        $this->assertFileExists($legacyDestDir . '/source.xhtml');

        $manifestBase = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));
        $sourceManifest = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$source->folder}/images_source_{$manifestBase}.json");
        $targetManifest = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$target->folder}/images_target_{$manifestBase}.json");

        $this->assertFileExists($sourceManifest);
        $this->assertFileExists($targetManifest);

        $publishedFacsimileDir = "/var/www/variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}";
        $this->assertFileExists($publishedFacsimileDir . '/img_' . $source->folder . '_001.jpg');

        $this->assertSame($sourceDir, storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}"));
    }

    public function test_unpublish_removes_published_components_and_clears_scope(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Dépublication critique',
            'short_title' => 'dpc',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1dpc']);
        $target = Version::factory()->for($work)->create(['folder' => '2dpc']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1dpc-2dpc-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $this->postJson('/api/publish_xhtml', [
            'comparison_id' => $comparison->id,
            'destination' => 'prod',
        ])->assertOk();

        $response = $this->deleteJson("/api/publish_xhtml/{$comparison->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'ok');

        $comparison->refresh();
        $this->assertNull($comparison->publication_scope);

        $destDir = storage_path("app/public/uploads/{$work->author->folder}/{$work->folder}/{$comparison->folder}");
        $this->assertDirectoryDoesNotExist($destDir);
    }

    public function test_export_legacy_zip_includes_dev_comparison_files_and_manifest_selected_images(): void
    {
        Queue::fake();

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Export dev legacy',
            'short_title' => 'edl',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1edl',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Target',
            'folder' => '2edl',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1edl-2edl-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<p>Source export</p>',
            'target.xhtml' => '<p>Target export</p>',
            'd.xhtml' => '<p>D export</p>',
            'i.xhtml' => '<p>I export</p>',
            'r.xhtml' => '<p>R export</p>',
            's.xhtml' => '<p>S export</p>',
        ]);

        $sourceFiles = $this->writeFacsimilePair($source);
        $targetFiles = $this->writeFacsimilePair($target);

        $this->postJson('/api/publish_xhtml', [
            'comparison_id' => $comparison->id,
            'destination' => 'dev',
        ])->assertOk();

        app(LegacyExportService::class)->deleteExportArtifacts($comparison->id);

        $queueResponse = $this->postJson("/comparisons/{$comparison->id}/export");
        $queueResponse->assertAccepted()
            ->assertJsonPath('status', 'queued');

        Queue::assertPushed(GenerateLegacyExportJob::class, function (GenerateLegacyExportJob $job) use ($comparison) {
            return $job->comparisonId === $comparison->id;
        });

        $statusResponse = $this->getJson("/comparisons/{$comparison->id}/export/status");
        $statusResponse->assertOk()
            ->assertJsonPath('status', 'queued');

        $snapshot = app(LegacyExportService::class)->createExportForComparison($comparison->fresh([
            'sourceVersion.work.author',
            'targetVersion.work.author',
        ]));

        $this->assertSame('ready', $snapshot['status'] ?? null);

        $response = $this->get("/comparisons/{$comparison->id}/export/download");
        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);

        $tmpZip = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpZip) === true);

        $this->assertNotFalse($zip->locateName('1edl-2edl-run1/source.xhtml'));
        $this->assertNotFalse($zip->locateName('1edl-2edl-run1/target.xhtml'));
        $this->assertNotFalse($zip->locateName('1edl-2edl-run1/d.xhtml'));
        $this->assertNotFalse($zip->locateName("1edl/{$sourceFiles['main']}"));
        $this->assertNotFalse($zip->locateName("1edl/{$sourceFiles['thumb']}"));
        $this->assertNotFalse($zip->locateName("2edl/{$targetFiles['main']}"));
        $this->assertNotFalse($zip->locateName("2edl/{$targetFiles['thumb']}"));

        $zip->close();
    }
}
