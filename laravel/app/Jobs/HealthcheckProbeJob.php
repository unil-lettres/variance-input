<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class HealthcheckProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $timestamp = now()->toIso8601String();
        Cache::put('health:probe:completed_at', $timestamp, 3600);
        Cache::forget('health:probe:pending');

        $path = storage_path('app/private/health_probe.txt');
        @file_put_contents($path, $timestamp . PHP_EOL);
    }
}
