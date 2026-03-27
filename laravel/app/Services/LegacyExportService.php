<?php

namespace App\Services;

use App\Http\Controllers\PublishController;
use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class LegacyExportService
{
    public function getSnapshot(int $comparisonId): array
    {
        $disk = Storage::disk('local');
        $relative = $this->statusRelativePath($comparisonId);
        $legacyRelative = $this->legacyStatusRelativePath($comparisonId);
        $resolvedRelative = $disk->exists($relative)
            ? $relative
            : ($disk->exists($legacyRelative) ? $legacyRelative : null);

        if (!$resolvedRelative) {
            return $this->emptySnapshot($comparisonId);
        }

        try {
            $decoded = json_decode($disk->get($resolvedRelative), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->emptySnapshot($comparisonId);
        }

        if (!is_array($decoded)) {
            return $this->emptySnapshot($comparisonId);
        }

        $snapshot = array_merge($this->emptySnapshot($comparisonId), $decoded);
        $snapshot['comparison_id'] = $comparisonId;

        $exportRelative = $snapshot['relative_path'] ?? null;
        if (($snapshot['status'] ?? null) === 'ready') {
            if (!is_string($exportRelative) || !$disk->exists($exportRelative)) {
                $snapshot = $this->emptySnapshot($comparisonId);
                $snapshot['status'] = 'missing';
                $snapshot['message'] = 'Archive introuvable. Relancez la préparation.';
            } else {
                $snapshot['size_bytes'] = (int) $disk->size($exportRelative);
            }
        }

        return $snapshot;
    }

    public function markQueued(Comparison $comparison): array
    {
        $snapshot = $this->emptySnapshot($comparison->id);
        $snapshot['status'] = 'queued';
        $snapshot['updated_at'] = now()->toIso8601String();
        $snapshot['message'] = 'Préparation de l’archive en attente.';
        $this->storeSnapshot($comparison->id, $snapshot);

        return $snapshot;
    }

    public function markRunning(Comparison $comparison): array
    {
        $snapshot = $this->getSnapshot($comparison->id);
        $snapshot['status'] = 'running';
        $snapshot['updated_at'] = now()->toIso8601String();
        $snapshot['message'] = 'Préparation de l’archive en cours.';
        $this->storeSnapshot($comparison->id, $snapshot);

        return $snapshot;
    }

    public function markFailed(Comparison $comparison, string $message): array
    {
        $snapshot = $this->getSnapshot($comparison->id);
        $snapshot['status'] = 'failed';
        $snapshot['updated_at'] = now()->toIso8601String();
        $snapshot['message'] = $message;
        $this->storeSnapshot($comparison->id, $snapshot);

        return $snapshot;
    }

    public function createExportForComparison(Comparison $comparison): array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        $author = $work?->author;
        if (!$work || !$author) {
            throw new \RuntimeException('Impossible de déterminer le dossier legacy à exporter.');
        }

        $sourceFolder = $this->resolvePublishedVersionFolder($comparison->sourceVersion);
        $targetFolder = $this->resolvePublishedVersionFolder($comparison->targetVersion);
        $comparisonExport = $this->resolvePublishedComparisonExport($comparison);

        $zipName = ($comparison->folder ?: 'comparison_' . $comparison->id) . '_legacy.zip';
        $disk = Storage::disk('local');
        $exportDirRelative = $this->exportDirRelativePath($comparison->id);
        $exportDirAbsolute = $disk->path($exportDirRelative);
        File::ensureDirectoryExists($exportDirAbsolute);

        $tmpPath = tempnam($exportDirAbsolute, 'legacy_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Impossible de préparer un fichier temporaire pour l’export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);
            throw new \RuntimeException('Impossible de créer l’archive d’export.');
        }

        $filesAdded = 0;

        if ($sourceFolder) {
            $filesAdded += $this->addManifestedFacsimilesToZip($zip, $comparison, $comparison->sourceVersion, 'source');
        }
        if ($targetFolder) {
            $filesAdded += $this->addManifestedFacsimilesToZip($zip, $comparison, $comparison->targetVersion, 'target');
        }
        if ($comparisonExport) {
            $filesAdded += $this->addDirectoryToZip(
                $zip,
                $comparisonExport['absolute_path'],
                $comparisonExport['zip_root']
            );
        }

        $zip->close();

        if ($filesAdded === 0) {
            @unlink($tmpPath);
            throw new \RuntimeException('Les dossiers publiés sont vides pour cette comparaison.');
        }

        $finalRelative = $exportDirRelative . '/' . $zipName;
        $finalAbsolute = $disk->path($finalRelative);
        File::ensureDirectoryExists(dirname($finalAbsolute));
        if (is_file($finalAbsolute)) {
            @unlink($finalAbsolute);
        }
        if (!@rename($tmpPath, $finalAbsolute)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Impossible de finaliser l’archive d’export.');
        }

        $snapshot = $this->emptySnapshot($comparison->id);
        $snapshot['status'] = 'ready';
        $snapshot['relative_path'] = $finalRelative;
        $snapshot['file_name'] = $zipName;
        $snapshot['size_bytes'] = (int) filesize($finalAbsolute);
        $snapshot['generated_at'] = now()->toIso8601String();
        $snapshot['updated_at'] = $snapshot['generated_at'];
        $snapshot['message'] = 'Archive prête au téléchargement.';
        $this->storeSnapshot($comparison->id, $snapshot);

        return $snapshot;
    }

    public function absolutePathFromSnapshot(array $snapshot): ?string
    {
        $relative = $snapshot['relative_path'] ?? null;
        if (!is_string($relative) || $relative === '') {
            return null;
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($relative)) {
            return null;
        }

        return $disk->path($relative);
    }

    public function deleteExportArtifacts(int $comparisonId): void
    {
        $disk = Storage::disk('local');

        foreach ([
            $this->statusRelativePath($comparisonId),
            $this->legacyStatusRelativePath($comparisonId),
        ] as $statusPath) {
            if ($disk->exists($statusPath)) {
                $disk->delete($statusPath);
            }
        }

        foreach ([
            $this->exportDirRelativePath($comparisonId),
            $this->legacyExportDirRelativePath($comparisonId),
        ] as $directoryPath) {
            if ($disk->exists($directoryPath)) {
                $disk->deleteDirectory($directoryPath);
            }
        }
    }

    private function emptySnapshot(int $comparisonId): array
    {
        return [
            'comparison_id' => $comparisonId,
            'status' => 'idle',
            'message' => null,
            'file_name' => null,
            'relative_path' => null,
            'size_bytes' => null,
            'generated_at' => null,
            'updated_at' => null,
        ];
    }

    private function statusRelativePath(int $comparisonId): string
    {
        return "exports/comparisons/{$comparisonId}.json";
    }

    private function exportDirRelativePath(int $comparisonId): string
    {
        return "exports/comparisons/{$comparisonId}";
    }

    private function legacyStatusRelativePath(int $comparisonId): string
    {
        return "private/exports/comparisons/{$comparisonId}.json";
    }

    private function legacyExportDirRelativePath(int $comparisonId): string
    {
        return "private/exports/comparisons/{$comparisonId}";
    }

    private function storeSnapshot(int $comparisonId, array $snapshot): void
    {
        $relative = $this->statusRelativePath($comparisonId);
        $disk = Storage::disk('local');
        $disk->put(
            $relative,
            json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $legacyRelative = $this->legacyStatusRelativePath($comparisonId);
        if ($disk->exists($legacyRelative)) {
            $disk->delete($legacyRelative);
        }
    }

    private function resolvePublishedVersionFolder(?Version $version): ?string
    {
        if (!$version) {
            return null;
        }

        $version->loadMissing('work.author', 'work');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        $versionFolder = $version->folder ?? null;

        if (!$authorFolder || !$workFolder || !$versionFolder) {
            return null;
        }

        $relative = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";

        return Storage::disk('public')->exists($relative) ? $relative : null;
    }

    private function resolvePublishedComparisonFolder(Comparison $comparison): ?string
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        $author = $work?->author;

        if (!$work || !$author || !$comparison->folder) {
            return null;
        }

        $relative = "uploads/{$author->folder}/{$work->folder}/{$comparison->folder}";

        return Storage::disk('public')->exists($relative) ? $relative : null;
    }

    private function resolvePublishedComparisonExport(Comparison $comparison): ?array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $zipRoot = $comparison->folder ?: ('comparison_' . $comparison->id);
        $scope = $comparison->publication_scope ?? null;

        if ($scope === 'dev') {
            $paths = $this->resolvePublicationPaths($comparison);
            $sourceDir = $paths['source_dir'] ?? null;
            if (is_string($sourceDir) && is_dir($sourceDir)) {
                return [
                    'absolute_path' => $sourceDir,
                    'zip_root' => $zipRoot,
                ];
            }
        }

        $publishedFolder = $this->resolvePublishedComparisonFolder($comparison);
        if ($publishedFolder && Storage::disk('public')->exists($publishedFolder)) {
            return [
                'absolute_path' => Storage::disk('public')->path($publishedFolder),
                'zip_root' => $zipRoot,
            ];
        }

        if ($scope === 'prod') {
            return null;
        }

        $paths = $this->resolvePublicationPaths($comparison);
        $sourceDir = $paths['source_dir'] ?? null;
        if (is_string($sourceDir) && is_dir($sourceDir)) {
            return [
                'absolute_path' => $sourceDir,
                'zip_root' => $zipRoot,
            ];
        }

        return null;
    }

    private function resolvePublicationPaths(Comparison $comparison): array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $sourceVersion = $comparison->sourceVersion;
        $work = $sourceVersion?->work;
        $author = $work?->author;

        if (!$sourceVersion || !$work || !$author) {
            return ['source_dir' => storage_path("app/public/uploads/comparisons/{$comparison->id}")];
        }

        $basePath = "uploads/{$author->folder}/{$work->folder}";
        $sourceDir = storage_path("app/public/{$basePath}/comparisons/{$comparison->id}");
        if (!is_dir($sourceDir)) {
            $legacy = public_path("{$basePath}/comparisons/{$comparison->id}");
            if (is_dir($legacy)) {
                $sourceDir = $legacy;
            }
        }

        return ['source_dir' => $sourceDir];
    }

    private function addManifestedFacsimilesToZip(ZipArchive $zip, Comparison $comparison, ?Version $version, string $role): int
    {
        if (!$version) {
            return 0;
        }

        $version->loadMissing('work.author', 'work');

        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        $versionFolder = $version->folder ?? null;
        $comparisonFolder = $comparison->folder;

        if (!$authorFolder || !$workFolder || !$versionFolder || !$comparisonFolder) {
            return 0;
        }

        $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparisonFolder));
        $manifestRelative = "{$relativeDir}/images_{$role}_{$baseName}.json";

        $disk = Storage::disk('public');
        if (!$disk->exists($manifestRelative)) {
            return 0;
        }

        $entries = json_decode($disk->get($manifestRelative), true);
        if (!is_array($entries)) {
            $entries = [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (['big', 'small'] as $key) {
                $value = $entry[$key] ?? null;
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $normalized = $this->normalizeManifestAssetPath($value, $relativeDir);
                if ($normalized === null) {
                    continue;
                }
                $files[$normalized] = true;
            }
        }

        $zipRoot = $versionFolder;
        $added = 0;

        $manifestAbsolute = $disk->path($manifestRelative);
        if (is_file($manifestAbsolute)) {
            $zip->addFile($manifestAbsolute, $zipRoot . '/' . basename($manifestRelative));
            $added++;
        }

        foreach (array_keys($files) as $fileRelative) {
            if (!$disk->exists($fileRelative)) {
                continue;
            }
            $absolute = $disk->path($fileRelative);
            $relativeInsideVersion = ltrim(str_replace($relativeDir, '', $fileRelative), '/');
            if ($relativeInsideVersion === '') {
                $relativeInsideVersion = basename($fileRelative);
            }
            $zip->addFile($absolute, $zipRoot . '/' . $relativeInsideVersion);
            $added++;
        }

        return $added;
    }

    private function normalizeManifestAssetPath(string $value, string $relativeDir): ?string
    {
        $normalized = ltrim(trim($value), '/');
        if ($normalized === '') {
            return null;
        }

        foreach (['admin/storage/', 'storage/', 'public/'] as $prefix) {
            if (str_starts_with($normalized, $prefix . 'uploads/')) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return $normalized;
        }

        return $relativeDir . '/' . basename($normalized);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $absolutePath, string $zipRoot): int
    {
        if (!is_dir($absolutePath)) {
            return 0;
        }

        $zipRoot = trim($zipRoot, '/\\');
        if ($zipRoot === '') {
            $zipRoot = basename($absolutePath);
        }

        $zip->addEmptyDir($zipRoot);
        $added = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolutePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative = ltrim(str_replace($absolutePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $entryName = $zipRoot . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            if ($file->isDir()) {
                $zip->addEmptyDir($entryName);
            } else {
                $zip->addFile($file->getPathname(), $entryName);
                $added++;
            }
        }

        return $added;
    }
}
