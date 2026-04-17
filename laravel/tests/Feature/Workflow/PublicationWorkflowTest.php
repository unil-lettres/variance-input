<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Mockery;
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

        $publishedFacsimileDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
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

    public function test_publish_dev_skips_recopy_when_legacy_draft_is_already_synced(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication dev déjà synchronisée',
            'short_title' => 'pdds',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pdds',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pdds',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pdds-2pdds-run1',
            'created_by' => $user->id,
        ]);

        $sourceDir = $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<p>Source synced</p>',
            'target.xhtml' => '<p>Target synced</p>',
        ]);
        $this->writeFacsimilePair($source);
        $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $legacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}");

        File::ensureDirectoryExists($legacyDir);
        foreach (File::files($sourceDir) as $file) {
            File::copy($file->getPathname(), $legacyDir . DIRECTORY_SEPARATOR . $file->getFilename());
        }

        chmod($legacyDir, 0555);
        foreach (File::files($legacyDir) as $file) {
            chmod($file->getPathname(), 0444);
        }

        try {
            $response = $this->postJson('/api/publish_xhtml', [
                'comparison_id' => $comparison->id,
                'destination' => 'dev',
            ]);
        } finally {
            chmod($legacyDir, 0775);
            foreach (File::files($legacyDir) as $file) {
                chmod($file->getPathname(), 0664);
            }
        }

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('published_to', 'dev')
            ->assertJsonPath('draft_mirror.status', 'skipped')
            ->assertJsonPath('draft_mirror.reason', 'already_synced');

        $comparison->refresh();
        $this->assertSame('dev', $comparison->publication_scope);
    }

    public function test_publish_dev_ignores_editor_backup_files_in_legacy_mirror(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication dev sans backups',
            'short_title' => 'pdsb',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pdsb',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pdsb',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pdsb-2pdsb-run1',
            'created_by' => $user->id,
        ]);

        $sourceDir = $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<p>Source changed</p>',
            'target.xhtml' => '<p>Target changed</p>',
        ]);
        File::put($sourceDir . '/source.original.xhtml', '<p>Backup source</p>');
        File::put($sourceDir . '/target.original.xhtml', '<p>Backup target</p>');

        $this->writeFacsimilePair($source);
        $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $legacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}");

        File::ensureDirectoryExists($legacyDir);
        foreach (['source.xhtml', 'target.xhtml', 'd.xhtml', 'i.xhtml', 'r.xhtml', 's.xhtml'] as $name) {
            File::copy($sourceDir . DIRECTORY_SEPARATOR . $name, $legacyDir . DIRECTORY_SEPARATOR . $name);
        }

        File::put($legacyDir . '/source.original.xhtml', '<p>Old backup source</p>');
        File::put($legacyDir . '/target.original.xhtml', '<p>Old backup target</p>');
        chmod($legacyDir . '/source.original.xhtml', 0444);
        chmod($legacyDir . '/target.original.xhtml', 0444);

        File::put($sourceDir . '/source.xhtml', '<p>Source changed again</p>');

        try {
            $response = $this->postJson('/api/publish_xhtml', [
                'comparison_id' => $comparison->id,
                'destination' => 'dev',
            ]);
        } finally {
            chmod($legacyDir . '/source.original.xhtml', 0664);
            chmod($legacyDir . '/target.original.xhtml', 0664);
        }

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft_mirror.status', 'ok');

        $this->assertSame('<p>Old backup source</p>', trim(File::get($legacyDir . '/source.original.xhtml')));
        $this->assertStringContainsString('Source changed again', File::get($legacyDir . '/source.xhtml'));
    }

    public function test_publish_replaces_read_only_legacy_manifest_via_directory_write(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication manifest legacy',
            'short_title' => 'pml',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pml',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pml',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pml-2pml-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $this->writeFacsimilePair($source);
        $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $manifestBase = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));
        $legacyVersionDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
        $legacyManifest = $legacyVersionDir . "/images_source_{$manifestBase}.json";
        $storageManifest = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$source->folder}/images_source_{$manifestBase}.json");

        File::ensureDirectoryExists($legacyVersionDir);
        File::ensureDirectoryExists(dirname($storageManifest));
        File::put($storageManifest, json_encode([['small' => '/fresh-thumb.jpg']]));
        File::put($legacyManifest, json_encode([['small' => '/stale-thumb.jpg']]));
        chmod($legacyManifest, 0444);

        try {
            $response = $this->postJson('/api/publish_xhtml', [
                'comparison_id' => $comparison->id,
                'destination' => 'dev',
            ]);
        } finally {
            @chmod($legacyManifest, 0664);
        }

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('published_to', 'dev');

        $this->assertFileExists($storageManifest);
        if (is_file($legacyManifest)) {
            $this->assertSame(File::get($storageManifest), File::get($legacyManifest));
        }
    }

    public function test_publish_skips_facsimile_copy_when_legacy_path_is_same_mount(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication facsimiles same mount',
            'short_title' => 'pfsm',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pfsm',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pfsm',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pfsm-2pfsm-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $sourceFiles = $this->writeFacsimilePair($source);
        $targetFiles = $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;

        $sourceStorageDir = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
        $targetStorageDir = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$target->folder}");
        $sourceLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
        $targetLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$target->folder}");

        File::deleteDirectory($sourceLegacyDir);
        File::deleteDirectory($targetLegacyDir);
        File::ensureDirectoryExists(dirname($sourceLegacyDir));
        File::ensureDirectoryExists(dirname($targetLegacyDir));
        symlink($sourceStorageDir, $sourceLegacyDir);
        symlink($targetStorageDir, $targetLegacyDir);

        try {
            $response = $this->postJson('/api/publish_xhtml', [
                'comparison_id' => $comparison->id,
                'destination' => 'dev',
            ]);
        } finally {
            if (is_link($sourceLegacyDir)) {
                unlink($sourceLegacyDir);
            }
            if (is_link($targetLegacyDir)) {
                unlink($targetLegacyDir);
            }
        }

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('facsimiles.source.status', 'skipped')
            ->assertJsonPath('facsimiles.source.reason', 'same_mount')
            ->assertJsonPath('facsimiles.target.status', 'skipped')
            ->assertJsonPath('facsimiles.target.reason', 'same_mount');

        $this->assertFileExists($sourceStorageDir . DIRECTORY_SEPARATOR . $sourceFiles['main']);
        $this->assertFileExists($targetStorageDir . DIRECTORY_SEPARATOR . $targetFiles['main']);
    }

    public function test_publish_dev_keeps_legacy_manifests_after_facsimile_publish(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication dev manifestes legacy',
            'short_title' => 'pdml',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pdml',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pdml',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pdml-2pdml-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $this->writeFacsimilePair($source);
        $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $manifestBase = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparison->folder));
        $sourceLegacyManifest = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}/images_source_{$manifestBase}.json");
        $targetLegacyManifest = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$target->folder}/images_target_{$manifestBase}.json");

        $this->postJson('/api/publish_xhtml', [
            'comparison_id' => $comparison->id,
            'destination' => 'dev',
        ])->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertFileExists($sourceLegacyManifest);
        $this->assertFileExists($targetLegacyManifest);
        $this->assertStringContainsString('img_' . $source->folder . '_001.jpg', File::get($sourceLegacyManifest));
        $this->assertStringContainsString('img_' . $target->folder . '_001.jpg', File::get($targetLegacyManifest));
    }

    public function test_publish_skips_already_synced_read_only_legacy_facsimiles(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication facsimiles déjà synchronisés',
            'short_title' => 'pfds',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pfds',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pfds',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pfds-2pfds-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $sourceFiles = $this->writeFacsimilePair($source);
        $targetFiles = $this->writeFacsimilePair($target);

        $this->postJson('/api/publish_xhtml', [
            'comparison_id' => $comparison->id,
            'destination' => 'dev',
        ])->assertOk();

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $sourceLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
        $targetLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$target->folder}");

        chmod(dirname($sourceLegacyDir), 0775);
        chmod(dirname($targetLegacyDir), 0775);
        chmod($sourceLegacyDir, 0555);
        chmod($targetLegacyDir, 0555);

        try {
            $response = $this->postJson('/api/publish_xhtml', [
                'comparison_id' => $comparison->id,
                'destination' => 'dev',
            ]);
        } finally {
            @chmod($sourceLegacyDir, 0775);
            @chmod($targetLegacyDir, 0775);
        }

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('facsimiles.source.status', 'skipped')
            ->assertJsonPath('facsimiles.source.reason', 'already_synced')
            ->assertJsonPath('facsimiles.target.status', 'skipped')
            ->assertJsonPath('facsimiles.target.reason', 'already_synced');

        $this->assertFileExists($sourceLegacyDir . DIRECTORY_SEPARATOR . $sourceFiles['main']);
        $this->assertFileExists($targetLegacyDir . DIRECTORY_SEPARATOR . $targetFiles['main']);
    }

    public function test_publish_recreates_unwritable_legacy_facsimile_directory(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication facsimiles unwritable dir',
            'short_title' => 'pfud',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pfud',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pfud',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pfud-2pfud-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $sourceFiles = $this->writeFacsimilePair($source);
        $targetFiles = $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $sourceLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
        $targetLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$target->folder}");

        File::ensureDirectoryExists($sourceLegacyDir);
        File::ensureDirectoryExists($targetLegacyDir);
        File::put($sourceLegacyDir . '/stale.json', '{}');
        File::put($targetLegacyDir . '/stale.json', '{}');
        chmod($sourceLegacyDir, 0555);
        chmod($targetLegacyDir, 0555);

        try {
            $response = $this->postJson('/api/publish_xhtml', [
                'comparison_id' => $comparison->id,
                'destination' => 'dev',
            ]);
        } finally {
            @chmod($sourceLegacyDir, 0775);
            @chmod($targetLegacyDir, 0775);
        }

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('facsimiles.source.status', 'ok')
            ->assertJsonPath('facsimiles.target.status', 'ok');

        $this->assertFileExists($sourceLegacyDir . DIRECTORY_SEPARATOR . $sourceFiles['main']);
        $this->assertFileExists($targetLegacyDir . DIRECTORY_SEPARATOR . $targetFiles['main']);
    }

    public function test_publish_succeeds_with_warning_when_legacy_facsimile_copy_fails(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Publication facsimiles warning',
            'short_title' => 'pfw',
        ]);
        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1pfw',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2pfw',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pfw-2pfw-run1',
            'created_by' => $user->id,
        ]);

        $this->writeComparisonArtifacts($comparison);
        $this->writeFacsimilePair($source);
        $this->writeFacsimilePair($target);

        $authorFolder = $work->author->folder;
        $workFolder = $work->folder;
        $sourceLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$source->folder}");
        $targetLegacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$target->folder}");

        File::partialMock()
            ->shouldReceive('put')
            ->withArgs(function (string $path) use ($sourceLegacyDir) {
                return str_starts_with($path, $sourceLegacyDir . DIRECTORY_SEPARATOR);
            })
            ->andThrow(new \RuntimeException('legacy denied'));

        $response = $this->postJson('/api/publish_xhtml', [
            'comparison_id' => $comparison->id,
            'destination' => 'dev',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('published_to', 'dev')
            ->assertJsonPath('facsimiles.source.status', 'warning')
            ->assertJsonPath('facsimiles.source.reason', 'copy_failed')
            ->assertJsonPath('facsimiles.target.status', 'ok')
            ->assertJsonCount(1, 'warnings');

        $comparison->refresh();
        $this->assertSame('dev', $comparison->publication_scope);
        $this->assertFileExists($targetLegacyDir . DIRECTORY_SEPARATOR . 'img_' . $target->folder . '_001.jpg');
    }
}
