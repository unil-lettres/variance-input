<?php

namespace Tests\Feature\Workflow;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('app/private/test-db-backups-' . uniqid());
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_backup_command_creates_compressed_dump_and_prunes_old_backups(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'variance',
            'username' => 'variance_user',
            'password' => 'variance_password',
        ]);

        $oldBackup = $this->tempDir . '/old_backup.sql.gz';
        file_put_contents($oldBackup, gzencode('-- obsolete --'));
        touch($oldBackup, now()->subDays(20)->getTimestamp());

        $binary = $this->tempDir . '/fake-mariadb-dump.sh';
        File::put($binary, <<<'SH'
#!/bin/sh
printf '%s\n' 'CREATE TABLE example (id INT);'
SH);
        chmod($binary, 0755);

        $this->artisan('backup:database', [
            '--output-dir' => $this->tempDir,
            '--binary' => $binary,
            '--retention-days' => 14,
        ])->assertSuccessful();

        $backups = glob($this->tempDir . '/*.sql.gz') ?: [];
        $this->assertCount(1, $backups);
        $this->assertFileDoesNotExist($oldBackup);

        $contents = gzdecode((string) file_get_contents($backups[0]));
        $this->assertStringContainsString('CREATE TABLE example', (string) $contents);
    }
}
