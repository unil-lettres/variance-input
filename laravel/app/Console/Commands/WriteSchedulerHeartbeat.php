<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WriteSchedulerHeartbeat extends Command
{
    protected $signature = 'health:scheduler-heartbeat';
    protected $description = 'Write a scheduler heartbeat timestamp for health checks.';

    public function handle(): int
    {
        $path = storage_path('app/private/scheduler_heartbeat.json');
        $payload = [
            'timestamp' => now()->toIso8601String(),
        ];
        @file_put_contents($path, json_encode($payload));

        $this->info('Scheduler heartbeat updated.');

        return self::SUCCESS;
    }
}
