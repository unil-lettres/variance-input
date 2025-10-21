<?php

namespace App\Jobs;

use App\Exceptions\PaginationCancelledException;
use App\Models\Version;
use App\Services\PageMarkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ApplyLignesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;
    /**
     * Safety: release the unique lock after N seconds in case a previous
     * attempt never started, so new runs are not blocked indefinitely.
     */
    public int $uniqueFor = 600; // 10 minutes

    public function __construct(
        public int $versionId,
        public ?string $storagePath = null,
        public bool $deleteAfter,
        public bool $clearExisting,
        public bool $replaceExisting,
        public ?int $comparisonId = null
    ) {
        $this->afterCommit();
        $this->onQueue('page-markers');
    }

    public function uniqueId(): string
    {
        return 'apply-lignes-' . $this->versionId . '-' . ($this->comparisonId ?? 'all');
    }

    public function handle(PageMarkerService $pageMarkerService): void
    {
        $disk = Storage::disk('local');
        $storagePath = null;
        try {
            $storagePath = $this->storagePath ?? null;
        } catch (\Error $e) {
            // Legacy payload without property, leave $storagePath = null
        }

        if (!$storagePath || !$disk->exists($storagePath)) {
            // Fallback for legacy jobs: attempt to locate the stored _lignes file automatically.
            $storagePath = $pageMarkerService->lignesRelativePath($this->versionId);
            if ($disk->exists($storagePath)) {
                // Update the job instance so subsequent retries reuse the discovered path.
                $this->storagePath = $storagePath;
                $this->deleteAfter = false;
            } else {
                $absoluteStored = $pageMarkerService->getStoredLignesAbsolutePath($this->versionId);
                if ($absoluteStored && file_exists($absoluteStored)) {
                    $this->storagePath = null;
                    $this->deleteAfter = false;
                    $absolute = $absoluteStored;
                    $this->performPagination($pageMarkerService, $absolute);
                    return;
                }

                $attempted = $this->storagePath ?? $storagePath;
                throw new \RuntimeException("Fichier _lignes introuvable ({$attempted}).");
            }
        }

        $absolute = $disk->path($storagePath);

        try {
            $this->performPagination($pageMarkerService, $absolute);
        } catch (PaginationCancelledException $e) {
            $pageMarkerService->markCancelled($this->versionId, $e->getMessage());
            return;
        }
    }

    private function performPagination(PageMarkerService $pageMarkerService, string $absolute): void
    {
        $disk = Storage::disk('local');

        try {
            $version = Version::findOrFail($this->versionId);
            $options = [
                'clear_existing'   => $this->clearExisting,
                'replace_existing' => $this->replaceExisting,
            ];
            if ($this->comparisonId) {
                $options['comparisons'] = [$this->comparisonId];
            }

            $pageMarkerService->applyLignesToVersion(
                $version,
                $absolute,
                $options
            );
        } catch (PaginationCancelledException $e) {
            $this->deleteAfter = false;
            throw $e;
        } finally {
            if ($this->deleteAfter && $this->storagePath && $disk->exists($this->storagePath)) {
                $disk->delete($this->storagePath);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        app(PageMarkerService::class)->markFailed($this->versionId, $exception->getMessage());
    }
}
