<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Version;
use App\Services\LegacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LegacyExportPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private string $exportRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportRoot = storage_path('app/private/export-permissions-' . bin2hex(random_bytes(6)));
        File::makeDirectory($this->exportRoot, 0775, true);
        config(['filesystems.disks.local.root' => $this->exportRoot]);
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportRoot);
        parent::tearDown();
    }

    public function test_fresh_export_is_readable_across_uids_but_not_outside_storage_group(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $source = Version::factory()->for($work)->create(['folder' => 'source-permissions']);
        $target = Version::factory()->for($work)->create(['folder' => 'target-permissions']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id, 'target_id' => $target->id,
            'folder' => 'permissions-export', 'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);
        $this->writeComparisonArtifacts($comparison);
        $service = app(LegacyExportService::class);
        $oldUmask = umask(0077);
        try {
            $service->markQueued($comparison);
            $snapshot = $service->createExportForComparison($comparison);
        } finally {
            umask($oldUmask);
        }
        $this->assertSame('ready', $snapshot['status']);
        $zip = Storage::disk('local')->path($snapshot['relative_path']);
        $status = $this->exportRoot . "/exports/comparisons/{$comparison->id}.json";
        foreach (['exports', 'exports/comparisons', "exports/comparisons/{$comparison->id}"] as $relative) {
            $path = $this->exportRoot . '/' . $relative;
            clearstatcache(true, $path);
            $this->assertSame(02770, fileperms($path) & 07777);
            $this->assertSame(filegroup(storage_path()), filegroup($path));
        }
        foreach ([$zip, $status] as $path) {
            clearstatcache(true, $path);
            $this->assertSame(0640, fileperms($path) & 0777);
            $this->assertSame(filegroup(storage_path()), filegroup($path));
        }
        $this->requireUidProbe();
        $code = <<<'PHP'
$snapshot = $service->getSnapshot($comparison->id);
if ($snapshot['status'] !== 'ready') { exit(10); }
$path = $service->absolutePathFromSnapshot($snapshot);
$response = new Symfony\Component\HttpFoundation\BinaryFileResponse($path);
$zip = new ZipArchive();
if ($zip->open($path) !== true || $zip->locateName('permissions-export/source.xhtml') === false) { exit(11); }
$zip->close();
echo 'readable';
PHP;
        $reader = $this->asUid(65534, (int) filegroup(storage_path()), $comparison->id, $code);
        $this->assertSame(0, $reader->getExitCode(), $reader->getErrorOutput());
        $this->assertSame('readable', $reader->getOutput());
        $outsider = $this->asUid(65533, 65533, $comparison->id,
            'echo is_readable($argv[5]) ? "exposed" : "denied";', $zip);
        $this->assertSame(0, $outsider->getExitCode(), $outsider->getErrorOutput());
        $this->assertSame('denied', $outsider->getOutput());
    }

    public function test_status_can_pass_between_two_unprivileged_users_without_world_access(): void
    {
        $this->requireUidProbe();
        chmod($this->exportRoot, 0770);
        chgrp($this->exportRoot, filegroup(storage_path()));
        foreach ([[65534, 'markQueued', 'queued'], [65533, 'markRunning', 'running'], [65534, 'markFailed', 'failed']] as [$uid, $method, $state]) {
            $code = '$snapshot = $service->' . $method . '($comparison, "synthetic failure"); echo $snapshot["status"];';
            $process = $this->asUid($uid, (int) filegroup(storage_path()), 7001, $code);
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame($state, $process->getOutput());
            $this->assertSame($state, app(LegacyExportService::class)->getSnapshot(7001)['status']);
        }
        $this->assertSame([], glob($this->exportRoot . '/exports/comparisons/status_*'));
    }

    public function test_owned_old_directories_are_repaired_without_changing_unrelated_private_files(): void
    {
        mkdir($this->exportRoot . '/exports', 0700);
        mkdir($this->exportRoot . '/exports/comparisons', 0700);
        $untouched = $this->exportRoot . '/unrelated';
        file_put_contents($untouched, 'private');
        chmod($untouched, 0600);
        $comparison = new Comparison();
        $comparison->id = 7001;
        app(LegacyExportService::class)->markQueued($comparison);
        foreach (['exports', 'exports/comparisons'] as $relative) {
            clearstatcache(true, $this->exportRoot . '/' . $relative);
            $this->assertSame(02770, fileperms($this->exportRoot . '/' . $relative) & 07777);
        }
        $this->assertSame(0600, fileperms($untouched) & 0777);
        $this->assertSame('private', file_get_contents($untouched));
    }

    public function test_export_directory_symlink_is_refused_without_writing_its_target(): void
    {
        mkdir($this->exportRoot . '/outside', 0700);
        symlink($this->exportRoot . '/outside', $this->exportRoot . '/exports');
        $comparison = new Comparison();
        $comparison->id = 7001;
        try {
            app(LegacyExportService::class)->markQueued($comparison);
            $this->fail('An export directory symlink must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertDirectoryDoesNotExist($this->exportRoot . '/outside/comparisons');
            $this->assertSame(0700, fileperms($this->exportRoot . '/outside') & 0777);
        }
    }

    public function test_status_write_failure_is_reported_and_temporary_file_is_removed(): void
    {
        mkdir($this->exportRoot . '/exports/comparisons/7001.json', 0770, true);
        $comparison = new Comparison();
        $comparison->id = 7001;
        try {
            app(LegacyExportService::class)->markQueued($comparison);
            $this->fail('A failed status replacement must not report a queued export.');
        } catch (\RuntimeException $e) {
            $this->assertSame([], glob($this->exportRoot . '/exports/comparisons/status_*'));
            $this->assertDirectoryExists($this->exportRoot . '/exports/comparisons/7001.json');
        }
    }

    public function test_unrepairable_directory_fails_without_reporting_success(): void
    {
        $this->requireUidProbe();
        mkdir($this->exportRoot . '/exports', 0700);
        $code = <<<'PHP'
try {
    $service->markQueued($comparison);
    exit(20);
} catch (RuntimeException $e) {
    echo 'refused';
}
PHP;
        $process = $this->asUid(65534, (int) filegroup(storage_path()), 7001, $code);
        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertSame('refused', $process->getOutput());
        $this->assertFileDoesNotExist($this->exportRoot . '/exports/comparisons/7001.json');
    }

    private function requireUidProbe(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !is_executable('/usr/bin/setpriv')) {
            $this->markTestSkipped('Cross-user checks require root and setpriv in an isolated Linux container.');
        }
    }

    private function asUid(int $uid, int $gid, int $comparisonId, string $action, string $extra = ''): Process
    {
        $bootstrap = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
$app = require $argv[1] . '/bootstrap/app.php';
$app->useStoragePath($argv[2]);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['filesystems.disks.local.root' => $argv[3]]);
Illuminate\Support\Facades\Storage::forgetDisk('local');
$service = app(App\Services\LegacyExportService::class);
$comparison = new App\Models\Comparison();
$comparison->id = (int) $argv[4];
umask(0077);
PHP;
        $process = new Process(['/usr/bin/setpriv', '--clear-groups', '--reuid=' . $uid, '--regid=' . $gid,
            PHP_BINARY, '-r', $bootstrap . "\n" . $action, base_path(), storage_path(), $this->exportRoot,
            (string) $comparisonId, $extra]);
        $process->run();
        return $process;
    }
}
