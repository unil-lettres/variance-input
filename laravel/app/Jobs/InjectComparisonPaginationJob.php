<?php

namespace App\Jobs;

use App\Models\Comparison;
use App\Models\Version;
use App\Services\PageMarkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InjectComparisonPaginationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;
    public int $uniqueFor = 600;

    public function __construct(
        public int $comparisonId,
        public bool $clearExisting = true,
        public bool $replaceExisting = true,
        public ?string $role = null
    ) {
        $this->afterCommit();
        $this->onQueue('page-markers');
    }

    public function uniqueId(): string
    {
        $suffix = $this->role ? strtolower($this->role) : 'all';
        return 'inject-pagination-' . $this->comparisonId . '-' . $suffix;
    }

    public function handle(PageMarkerService $pageMarkerService): void
    {
        $comparison = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])
            ->findOrFail($this->comparisonId);

        $role = $this->role ? strtolower($this->role) : null;

        try {
            if ($role) {
                $version = $role === 'target'
                    ? $comparison->targetVersion
                    : $comparison->sourceVersion;

                $pageMarkerService->applySidecarToComparisonRoleOnly(
                    $comparison,
                    $role,
                    $this->clearExisting,
                    $this->replaceExisting
                );
            } else {
                $pageMarkerService->markComparisonQueued($comparison->id);
                $pageMarkerService->applySidecarToComparison(
                    $comparison,
                    $this->clearExisting,
                    $this->replaceExisting
                );
            }

            $this->ensureManifests($comparison, $role);
        } catch (\Throwable $e) {
            $pageMarkerService->markComparisonFailed($comparison->id, $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('InjectComparisonPaginationJob failed', [
            'comparison_id' => $this->comparisonId,
            'error'         => $exception->getMessage(),
        ]);
    }

    private function ensureManifests(Comparison $comparison, ?string $role = null): void
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $work = $comparison->sourceVersion?->work ?? $comparison->targetVersion?->work;
        $authorFolder = $work?->author?->folder;
        $workFolder = $work?->folder;
        $comparisonFolder = $comparison->folder;
        if (!$authorFolder || !$workFolder || !$comparisonFolder) {
            return;
        }

        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparisonFolder));

        $roles = $role ? [strtolower($role)] : ['source', 'target'];
        foreach ($roles as $roleKey) {
            if (!in_array($roleKey, ['source', 'target'], true)) {
                continue;
            }
            $version = $roleKey === 'target' ? $comparison->targetVersion : $comparison->sourceVersion;
            if (!$version instanceof Version) {
                continue;
            }
            $versionFolder = $version->folder;
            if (!$versionFolder) {
                continue;
            }

            $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
            $filename = sprintf('images_%s_%s.json', $roleKey, $baseName);
            $storagePath = "{$relativeDir}/{$filename}";

            $entries = $this->loadExistingManifestEntries($storagePath);
            if ($entries === null) {
                $entries = $version->collectManifestEntries();
            }
            if (empty($entries)) {
                continue;
            }

            $payload = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            Storage::disk('public')->put($storagePath, $payload);
            $this->mirrorToLegacy($relativeDir, $filename, $payload);
        }
    }

    private function loadExistingManifestEntries(string $relativePath): ?array
    {
        $disk = Storage::disk('public');
        if ($disk->exists($relativePath)) {
            $entries = json_decode($disk->get($relativePath), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($entries)) {
                return $entries;
            }
        }

        $legacyPath = base_path('../variance/' . $relativePath);
        if (is_file($legacyPath)) {
            $entries = json_decode(File::get($legacyPath), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($entries)) {
                return $entries;
            }
        }

        return null;
    }

    private function mirrorToLegacy(string $relativeDir, string $fileName, string $contents): void
    {
        $legacyDir = base_path('../variance/' . $relativeDir);
        if (!is_dir($legacyDir)) {
            File::makeDirectory($legacyDir, 0775, true, true);
        }
        File::put($legacyDir . DIRECTORY_SEPARATOR . $fileName, $contents);
    }
}
