<?php

namespace App\Console\Commands;

use App\Models\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ImportLegacyVersionTexts extends Command
{
    protected $signature = 'variance:import-legacy-version-texts
        {--assets-root= : Legacy uploads root (defaults to ../variance/uploads)}
        {--apply : Write normalized TXT files into Laravel storage}
        {--version-id=* : Limit to one or more version IDs}';

    protected $description = 'Import matching legacy source TXT files for legacy versions into Laravel uploads/versions storage.';

    public function handle(): int
    {
        $assetsRoot = (string) ($this->option('assets-root') ?: base_path('../variance/uploads'));
        if (!is_dir($assetsRoot)) {
            $this->components->error("Legacy uploads root not found: {$assetsRoot}");
            return self::FAILURE;
        }

        $versionIds = collect((array) $this->option('version-id'))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        $versions = Version::query()
            ->with('work.author')
            ->where('is_legacy', true)
            ->when($versionIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $versionIds->all()))
            ->orderBy('id')
            ->get();

        if ($versions->isEmpty()) {
            $this->components->warn('Aucune version legacy trouvée pour cette sélection.');
            return self::SUCCESS;
        }

        $rows = [];
        $written = 0;

        foreach ($versions as $version) {
            $sourcePath = $this->resolveLegacyTextPath($assetsRoot, $version);
            $destinationRelative = "uploads/versions/{$version->folder}.txt";
            $destinationPath = storage_path("app/public/{$destinationRelative}");
            $alreadyPresent = is_file($destinationPath);

            $status = 'missing';
            $action = 'skip';
            $chars = null;

            if ($sourcePath !== null) {
                $status = 'match';
                $normalized = $this->readFileAsUtf8($sourcePath);
                $chars = mb_strlen($normalized, 'UTF-8');

                if ($this->option('apply')) {
                    Storage::disk('public')->put($destinationRelative, $normalized);
                    Cache::forget("versions:index:work:{$version->work_id}");
                    $action = $alreadyPresent ? 'overwritten' : 'written';
                    $written++;
                }
            }

            $rows[] = [
                $version->id,
                $version->folder,
                $version->name,
                $status,
                $action,
                $chars ?? '—',
                $sourcePath ? $this->relativePath($sourcePath) : '—',
            ];
        }

        $this->table(
            ['ID', 'Dossier', 'Version', 'Statut', 'Action', 'Car.', 'Source legacy'],
            $rows
        );

        $matched = collect($rows)->where(3, 'match')->count();
        $missing = collect($rows)->where(3, 'missing')->count();

        $this->newLine();
        $this->components->info(sprintf('Résumé : %d correspondances, %d introuvables.', $matched, $missing));

        if ($this->option('apply')) {
            $this->components->info(sprintf('%d fichier(s) TXT importé(s).', $written));
        } else {
            $this->components->warn('Mode dry-run : aucune écriture. Utilisez --apply pour importer les TXT normalisés.');
        }

        return self::SUCCESS;
    }

    private function resolveLegacyTextPath(string $assetsRoot, Version $version): ?string
    {
        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (!$authorFolder || !$workFolder) {
            return null;
        }

        $path = $assetsRoot . DIRECTORY_SEPARATOR . $authorFolder . DIRECTORY_SEPARATOR . $workFolder . DIRECTORY_SEPARATOR . $version->folder . '.txt';

        return is_file($path) ? $path : null;
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }

        return $path;
    }

    private function readFileAsUtf8(string $absPath): string
    {
        $bytes = file_get_contents($absPath);
        if ($bytes === false) {
            throw new \RuntimeException("Impossible de lire le fichier source : {$absPath}");
        }

        $enc = mb_detect_encoding(
            $bytes,
            ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'],
            true
        ) ?: 'Windows-1252';

        $utf8 = $this->convertToUtf8($bytes, $enc);
        $utf8 = $this->preferMacRomanIfCleaner($bytes, $enc, $utf8);

        if (!mb_check_encoding($utf8, 'UTF-8')) {
            $utf8 = $this->convertToUtf8($bytes, 'Macintosh');
        }

        return str_replace(["\r\n", "\r"], "\n", $utf8);
    }

    private function convertToUtf8(string $bytes, string $sourceEncoding): string
    {
        $source = trim($sourceEncoding) !== '' ? $sourceEncoding : 'Windows-1252';

        if (strcasecmp($source, 'Macintosh') === 0 && function_exists('iconv')) {
            $converted = @iconv('MACINTOSH', 'UTF-8//IGNORE', $bytes);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return mb_convert_encoding($bytes, 'UTF-8', $source);
    }

    private function preferMacRomanIfCleaner(string $bytes, string $detectedEncoding, string $decoded): string
    {
        $normalized = strtoupper(trim($detectedEncoding));
        if (!in_array($normalized, ['WINDOWS-1252', 'ISO-8859-1', 'ASCII'], true)) {
            return $decoded;
        }

        $macDecoded = $this->convertToUtf8($bytes, 'Macintosh');
        if ($macDecoded === '' || !mb_check_encoding($macDecoded, 'UTF-8')) {
            return $decoded;
        }

        $decodedScore = $this->decodedTextNoiseScore($decoded);
        $macScore = $this->decodedTextNoiseScore($macDecoded);

        return ($macScore + 3) < $decodedScore ? $macDecoded : $decoded;
    }

    private function decodedTextNoiseScore(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        $score = 0;
        $score += preg_match_all('/[\x{0080}-\x{009F}]/u', $content) * 10;
        $score += preg_match_all('/[\x{FFFD}]/u', $content) * 6;
        $score += preg_match_all('/[\x{00D5}\x{0152}\x{0153}\x{02C6}\x{0160}\x{2039}\x{203A}\x{0178}\x{017E}\x{2122}]/u', $content) * 2;

        return $score;
    }
}
