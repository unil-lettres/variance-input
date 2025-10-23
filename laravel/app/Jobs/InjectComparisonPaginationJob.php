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
        public bool $replaceExisting = true
    ) {
        $this->afterCommit();
        $this->onQueue('page-markers');
    }

    public function uniqueId(): string
    {
        return 'inject-pagination-' . $this->comparisonId;
    }

    public function handle(PageMarkerService $pageMarkerService): void
    {
        $comparison = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])
            ->findOrFail($this->comparisonId);

        $pageMarkerService->markComparisonQueued($comparison->id);

        try {
            $pageMarkerService->applySidecarToComparison(
                $comparison,
                $this->clearExisting,
                $this->replaceExisting
            );
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
