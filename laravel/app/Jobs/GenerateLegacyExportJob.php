<?php

namespace App\Jobs;

use App\Models\Comparison;
use App\Services\LegacyExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLegacyExportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(public int $comparisonId)
    {
    }

    public function uniqueId(): string
    {
        return 'legacy-export-' . $this->comparisonId;
    }

    public function handle(LegacyExportService $legacyExportService): void
    {
        $comparison = Comparison::with(['sourceVersion.work.author', 'targetVersion.work.author'])
            ->find($this->comparisonId);

        if (!$comparison) {
            Log::warning('Legacy export comparison missing', ['comparison_id' => $this->comparisonId]);
            return;
        }

        $legacyExportService->markRunning($comparison);
        $legacyExportService->createExportForComparison($comparison);
    }

    public function failed(\Throwable $exception): void
    {
        $comparison = Comparison::find($this->comparisonId);
        if (!$comparison) {
            return;
        }

        app(LegacyExportService::class)->markFailed($comparison, $exception->getMessage());
    }
}
