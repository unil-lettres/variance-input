<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TaskMonitorController extends Controller
{
    public function index()
    {
        $queues = ['default', 'facsimiles', 'page-markers'];
        $queueStats = [];

        $queueDriver = config('queue.default');

        if ($queueDriver === 'redis') {
            $redis = \Illuminate\Support\Facades\Redis::connection();

            foreach ($queues as $queue) {
                $pendingKey = "queues:{$queue}";
                $delayedKey = "queues:{$queue}:delayed";
                $reservedKey = "queues:{$queue}:reserved";

                $queueStats[] = [
                    'queue'    => $queue,
                    'pending'  => (int) $redis->llen($pendingKey),
                    'delayed'  => (int) $redis->zcard($delayedKey),
                    'reserved' => (int) $redis->zcard($reservedKey),
                ];
            }
        } else {
            $now = now()->timestamp;

            foreach ($queues as $queue) {
                $pending = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->where('available_at', '<=', $now)
                    ->count();

                $delayed = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->where('available_at', '>', $now)
                    ->count();

                $reserved = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNotNull('reserved_at')
                    ->count();

                $queueStats[] = [
                    'queue'    => $queue,
                    'pending'  => $pending,
                    'delayed'  => $delayed,
                    'reserved' => $reserved,
                ];
            }
        }

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get();

        return view('pages.tasks', [
            'queueStats'  => $queueStats,
            'failedJobs'  => $failedJobs,
            'queueDriver' => $queueDriver,
        ]);
    }
}
