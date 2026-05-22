<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
        {--retention-days=14 : Number of days to keep generated backups}
        {--output-dir= : Backup output directory (defaults to storage/app/private/db_backups)}
        {--binary= : Explicit path to mariadb-dump or mysqldump executable}';

    protected $description = 'Create a compressed SQL backup of the configured database and prune expired dumps.';

    public function handle(): int
    {
        $connectionName = (string) config('database.default', 'mysql');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->components->error("Connexion de base introuvable : {$connectionName}");

            return self::FAILURE;
        }

        $database = trim((string) ($connection['database'] ?? ''));
        $username = trim((string) ($connection['username'] ?? ''));
        $password = (string) ($connection['password'] ?? '');
        if ($database === '' || $username === '') {
            $this->components->error('Configuration base de données incomplète pour le backup.');

            return self::FAILURE;
        }

        $retentionDays = max(1, (int) $this->option('retention-days'));
        $outputDir = $this->resolveOutputDir((string) ($this->option('output-dir') ?? ''));
        $binary = $this->resolveDumpBinary((string) ($this->option('binary') ?? ''));

        if ($binary === null) {
            $this->components->error('Impossible de trouver mariadb-dump ou mysqldump.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($outputDir);

        $backupPath = $outputDir . DIRECTORY_SEPARATOR . $this->backupFileName($database);
        $command = $this->buildDumpCommand($binary, $connection, $database, $username);
        $result = $this->streamDumpToGzip($command, $backupPath, $password);

        if (! ($result['ok'] ?? false)) {
            if (is_file($backupPath)) {
                @unlink($backupPath);
            }

            $this->components->error('Échec du backup SQL.');
            if (! empty($result['error'])) {
                $this->line(trim((string) $result['error']));
            }

            return self::FAILURE;
        }

        $pruned = $this->pruneExpiredBackups($outputDir, $retentionDays);

        $this->components->info('Backup SQL créé.');
        $this->line('Fichier : ' . $backupPath);
        $this->line('Taille : ' . $this->formatBytes(@filesize($backupPath) ?: 0));
        $this->line('Rétention : ' . $retentionDays . ' jour(s)');
        $this->line('Purgés : ' . $pruned);

        return self::SUCCESS;
    }

    private function resolveOutputDir(string $option): string
    {
        $dir = trim($option);

        return $dir !== ''
            ? $dir
            : storage_path('app/private/db_backups');
    }

    private function backupFileName(string $database): string
    {
        $prefix = Str::slug(config('app.name', 'variance'), '_') ?: 'variance';

        return sprintf(
            '%s_%s_%s.sql.gz',
            $prefix,
            Str::slug($database, '_') ?: 'database',
            now()->format('Ymd_His')
        );
    }

    private function resolveDumpBinary(string $explicit): ?string
    {
        $explicit = trim($explicit);
        if ($explicit !== '') {
            return is_file($explicit) && is_executable($explicit) ? $explicit : null;
        }

        foreach (['mariadb-dump', 'mysqldump'] as $candidate) {
            $resolved = $this->findExecutable($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function findExecutable(string $binary): ?string
    {
        $path = (string) getenv('PATH');
        if ($path === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $directory = trim($directory);
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildDumpCommand(string $binary, array $connection, string $database, string $username): array
    {
        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--user=' . $username,
        ];

        $socket = trim((string) ($connection['unix_socket'] ?? ''));
        $host = trim((string) ($connection['host'] ?? ''));
        $port = (string) ($connection['port'] ?? '3306');

        if ($socket !== '') {
            $command[] = '--socket=' . $socket;
        } else {
            $command[] = '--protocol=TCP';
            $command[] = '--host=' . ($host !== '' ? $host : '127.0.0.1');
            $command[] = '--port=' . ($port !== '' ? $port : '3306');
        }

        $command[] = $database;

        return $command;
    }

    private function streamDumpToGzip(array $command, string $backupPath, string $password): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $this->sanitizedProcessEnv($password);
        $commandLine = implode(' ', array_map('escapeshellarg', $command));

        $process = proc_open($commandLine, $descriptors, $pipes, null, $env);
        if (! is_resource($process)) {
            return [
                'ok' => false,
                'error' => 'Impossible de démarrer mariadb-dump.',
            ];
        }

        fclose($pipes[0]);

        $gzip = gzopen($backupPath, 'wb9');
        if ($gzip === false) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return [
                'ok' => false,
                'error' => 'Impossible de créer le fichier de backup compressé.',
            ];
        }

        try {
            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], 1024 * 1024);
                if ($chunk === false) {
                    break;
                }

                if ($chunk !== '') {
                    gzwrite($gzip, $chunk);
                }
            }

            $stderr = stream_get_contents($pipes[2]) ?: '';
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            gzclose($gzip);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return [
                'ok' => false,
                'error' => trim($stderr) !== '' ? trim($stderr) : 'mariadb-dump a échoué.',
            ];
        }

        return [
            'ok' => true,
            'error' => trim($stderr) !== '' ? trim($stderr) : null,
        ];
    }

    private function pruneExpiredBackups(string $outputDir, int $retentionDays): int
    {
        $cutoff = Carbon::now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*.sql.gz') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            $mtime = @filemtime($file);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;

        foreach ($units as $unit) {
            if ($value < 1024) {
                return sprintf('%.1f %s', $value, $unit);
            }

            $value /= 1024;
        }

        return sprintf('%.1f %s', $value, end($units));
    }

    private function sanitizedProcessEnv(string $password): array
    {
        $env = [];

        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        $env['MYSQL_PWD'] = $password;

        return $env;
    }
}
