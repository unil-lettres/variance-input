<?php

namespace App\Jobs;

use App\Models\Comparison;
use App\Services\PageMarkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
                if ($version) {
                    $pageMarkerService->markQueued($version->id);
                }

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
}
