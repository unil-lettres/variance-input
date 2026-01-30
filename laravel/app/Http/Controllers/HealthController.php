<?php

namespace App\Http\Controllers;

use App\Models\Comparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Carbon;
use App\Jobs\HealthcheckProbeJob;

class HealthController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$payload, $httpStatus] = $this->buildReport($this->resolveFailedWindowKey($request));

        return response()->json($payload, $httpStatus);
    }

    public function page(Request $request)
    {
        [$payload, $httpStatus] = $this->buildReport($this->resolveFailedWindowKey($request));

        return response()
            ->view('pages.health', $payload)
            ->setStatusCode($httpStatus);
    }

    private function buildReport(string $failedWindowKey): array
    {
        $checks = [];
        $status = 'ok';
        $httpStatus = 200;
        $failedWindowSeconds = $this->failedWindowSeconds($failedWindowKey);

        $checks['app'] = [
            'ok' => true,
            'env' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ];
        $checks['config'] = [
            'queue_connection' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'admin_base_path' => config('app.admin_base_path'),
        ];

        $dbOk = false;
        $startedAt = microtime(true);
        try {
            DB::select('select 1');
            $dbOk = true;
            $dbVersion = DB::selectOne('select version() as version');
            $checks['database'] = [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'connection' => config('database.default'),
                'server_version' => $dbVersion?->version ?? null,
            ];
        } catch (\Throwable $e) {
            $checks['database'] = [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
            $status = 'fail';
            $httpStatus = 503;
        }

        $storagePath = storage_path();
        $storageOk = is_dir($storagePath) && is_writable($storagePath);
        $storageFree = $this->freeSpace($storagePath);
        $warnGb = (int) env('HEALTHCHECK_DISK_WARN_GB', 10);
        $critGb = (int) env('HEALTHCHECK_DISK_CRIT_GB', 5);
        $warnBytes = $warnGb > 0 ? $warnGb * 1024 * 1024 * 1024 : null;
        $critBytes = $critGb > 0 ? $critGb * 1024 * 1024 * 1024 : null;
        $diskStatus = 'ok';
        if ($storageFree !== null) {
            if ($critBytes !== null && $storageFree < $critBytes) {
                $diskStatus = 'critical';
            } elseif ($warnBytes !== null && $storageFree < $warnBytes) {
                $diskStatus = 'warning';
            }
        }
        $checks['storage'] = [
            'ok' => $storageOk,
            'path' => $storagePath,
            'free_bytes' => $storageFree,
            'free_human' => $this->formatBytes($storageFree),
            'disk_status' => $diskStatus,
            'warn_gb' => $warnGb,
            'crit_gb' => $critGb,
        ];
        if (! $storageOk || $diskStatus === 'critical') {
            $this->markDegraded($status, $httpStatus);
        }
        if ($diskStatus === 'warning') {
            $this->markDegraded($status, $httpStatus);
        }

        $checks['paths'] = $this->checkPaths();
        if (! $checks['paths']['ok']) {
            $this->markDegraded($status, $httpStatus);
        }

        $publicPath = public_path();
        $publicOk = is_dir($publicPath) && is_readable($publicPath);
        $checks['public'] = [
            'ok' => $publicOk,
            'path' => $publicPath,
        ];
        if (! $publicOk) {
            $this->markDegraded($status, $httpStatus);
        }

        $cacheDriver = config('cache.default');
        try {
            $cacheKey = 'healthcheck:' . uniqid('', true);
            Cache::put($cacheKey, 'ok', 10);
            $cacheOk = Cache::get($cacheKey) === 'ok';
            Cache::forget($cacheKey);
            $checks['cache'] = [
                'ok' => $cacheOk,
                'driver' => $cacheDriver,
            ];
            if (! $cacheOk) {
                $this->markDegraded($status, $httpStatus);
            }
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'ok' => false,
                'driver' => $cacheDriver,
                'error' => $e->getMessage(),
            ];
            $this->markDegraded($status, $httpStatus);
        }

        $queueDriver = config('queue.default');
        $queueStats = [];
        $queueTotals = [
            'pending' => 0,
            'delayed' => 0,
            'reserved' => 0,
            'stale_reserved' => 0,
        ];
        if ($queueDriver === 'redis') {
            if (class_exists('Redis') || class_exists('Predis\\Client')) {
                try {
                    $redis = Redis::connection();
                    foreach (['default', 'facsimiles', 'page-markers'] as $queue) {
                        $stats = [
                            'queue' => $queue,
                            'pending' => (int) $redis->llen("queues:{$queue}"),
                            'delayed' => (int) $redis->zcard("queues:{$queue}:delayed"),
                            'reserved' => (int) $redis->zcard("queues:{$queue}:reserved"),
                            'stale_reserved' => 0,
                            'oldest_pending_at' => null,
                            'oldest_pending_age_seconds' => null,
                        ];
                        $queueTotals['pending'] += $stats['pending'];
                        $queueTotals['delayed'] += $stats['delayed'];
                        $queueTotals['reserved'] += $stats['reserved'];
                        $queueStats[] = $stats;
                    }
                    $checks['queue'] = [
                        'ok' => true,
                        'driver' => $queueDriver,
                        'stats' => $queueStats,
                        'totals' => $queueTotals,
                    ];
                } catch (\Throwable $e) {
                    $checks['queue'] = [
                        'ok' => false,
                        'driver' => $queueDriver,
                        'error' => $e->getMessage(),
                    ];
                    $this->markDegraded($status, $httpStatus);
                }
            } else {
                $checks['queue'] = [
                    'ok' => false,
                    'driver' => $queueDriver,
                    'error' => 'Redis extension/client not available',
                ];
                $this->markDegraded($status, $httpStatus);
            }
        } elseif ($dbOk) {
            $now = now()->timestamp;
            foreach (['default', 'facsimiles', 'page-markers'] as $queue) {
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

                $stale = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNotNull('reserved_at')
                    ->where('reserved_at', '<', now()->subMinutes(10)->timestamp)
                    ->count();

                $oldestPendingAt = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNull('reserved_at')
                    ->orderBy('available_at')
                    ->value('available_at');
                $oldestPendingAge = $oldestPendingAt ? max(0, $now - (int) $oldestPendingAt) : null;

                $stats = [
                    'queue' => $queue,
                    'pending' => $pending,
                    'delayed' => $delayed,
                    'reserved' => $reserved,
                    'stale_reserved' => $stale,
                    'oldest_pending_at' => $oldestPendingAt ? now()->setTimestamp((int) $oldestPendingAt)->toIso8601String() : null,
                    'oldest_pending_age_seconds' => $oldestPendingAge,
                ];
                $queueTotals['pending'] += $pending;
                $queueTotals['delayed'] += $delayed;
                $queueTotals['reserved'] += $reserved;
                $queueTotals['stale_reserved'] += $stale;
                $queueStats[] = $stats;
            }
            $checks['queue'] = [
                'ok' => true,
                'driver' => $queueDriver,
                'stats' => $queueStats,
                'totals' => $queueTotals,
            ];
        } else {
            $checks['queue'] = [
                'ok' => false,
                'driver' => $queueDriver,
                'error' => 'Database unavailable for queue stats',
            ];
            $this->markDegraded($status, $httpStatus);
        }

        $checks['workers'] = $this->deriveWorkerStatus($checks['queue'] ?? null);
        $checks['workers'] = $this->mergeWorkerHeartbeat($checks['workers']);
        $checks['worker_probe'] = $this->runWorkerProbe($queueDriver);
        if (($checks['worker_probe']['status'] ?? null) === 'stale') {
            $this->markDegraded($status, $httpStatus);
        }
        $checks['scheduler'] = $this->readSchedulerHeartbeat();
        if (! ($checks['scheduler']['ok'] ?? false)) {
            $this->markDegraded($status, $httpStatus);
        }

        if ($dbOk) {
            try {
                $prodCount = Comparison::where('publication_scope', 'prod')->count();
                $devCount = Comparison::where('publication_scope', 'dev')->count();
                $legacyProd = Comparison::whereNull('publication_scope')
                    ->where('is_legacy', true)
                    ->count();

                $checks['comparisons'] = [
                    'ok' => true,
                    'prod' => $prodCount + $legacyProd,
                    'dev' => $devCount,
                ];
            } catch (\Throwable $e) {
                $checks['comparisons'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        } else {
            $checks['comparisons'] = [
                'ok' => false,
                'error' => 'Database unavailable',
            ];
        }

        if ($dbOk) {
            try {
                $checks['db_counts'] = [
                    'ok' => true,
                    'users' => DB::table('users')->count(),
                    'authors' => DB::table('authors')->count(),
                    'works' => DB::table('works')->count(),
                    'versions' => DB::table('versions')->count(),
                    'comparisons' => DB::table('comparisons')->count(),
                ];
            } catch (\Throwable $e) {
                $checks['db_counts'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($dbOk) {
            try {
                $recentCutoff = now()->subSeconds($failedWindowSeconds);
                $checks['failed_jobs'] = [
                    'ok' => true,
                    'total' => DB::table('failed_jobs')->count(),
                    'recent' => DB::table('failed_jobs')
                        ->where('failed_at', '>=', $recentCutoff)
                        ->count(),
                    'latest_at' => DB::table('failed_jobs')->max('failed_at'),
                    'recent_latest_at' => DB::table('failed_jobs')
                        ->where('failed_at', '>=', $recentCutoff)
                        ->max('failed_at'),
                    'window' => $failedWindowKey,
                    'window_seconds' => $failedWindowSeconds,
                ];
            } catch (\Throwable $e) {
                $checks['failed_jobs'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $this->markDegraded($status, $httpStatus);
            }
        } else {
            $checks['failed_jobs'] = [
                'ok' => false,
                'error' => 'Database unavailable',
            ];
            $this->markDegraded($status, $httpStatus);
        }

        $mediteUrl = config('services.medite.health_url') ?? env('MEDITE_HEALTH_URL', 'http://medite:5000/health');
        try {
            $mediteStart = microtime(true);
            $response = Http::timeout(2)->get($mediteUrl);
            $checks['medite'] = [
                'ok' => $response->ok(),
                'status' => $response->status(),
                'latency_ms' => (int) round((microtime(true) - $mediteStart) * 1000),
                'url' => $mediteUrl,
                'body' => $response->ok() ? $response->json() : null,
            ];
            if (! $response->ok()) {
                $this->markDegraded($status, $httpStatus);
            }
        } catch (\Throwable $e) {
            $checks['medite'] = [
                'ok' => false,
                'status' => null,
                'url' => $mediteUrl,
                'error' => $e->getMessage(),
            ];
            $this->markDegraded($status, $httpStatus);
        }

        $checks['medite_probe'] = $this->runMediteProbe($mediteUrl);
        if (($checks['medite_probe']['status'] ?? null) === 'stale') {
            $this->markDegraded($status, $httpStatus);
        }

        $httpChecks = [];
        $httpTargets = $this->resolveHttpTargets();
        foreach ($httpTargets as $label => $url) {
            try {
                $response = Http::timeout(2)->get($url);
                $ok = $response->status() >= 200 && $response->status() < 400;
                $httpChecks[$label] = [
                    'ok' => $ok,
                    'status' => $response->status(),
                    'url' => $url,
                ];
                if (! $ok) {
                    $this->markDegraded($status, $httpStatus);
                }
            } catch (\Throwable $e) {
                $httpChecks[$label] = [
                    'ok' => false,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ];
                $this->markDegraded($status, $httpStatus);
            }
        }

        if (! empty($httpChecks)) {
            $checks['http'] = $httpChecks;
        }

        $timestamp = now();
        $timezone = 'Europe/Zurich';
        $timestampLocal = $timestamp->copy()->setTimezone($timezone);

        return [[
            'status' => $status,
            'timestamp' => $timestamp->toIso8601String(),
            'timestamp_local' => $timestampLocal->format('m/d/Y H:i'),
            'timezone' => $timezone,
            'checks' => $checks,
            'failed_window' => $failedWindowKey,
        ], $httpStatus];
    }

    private function freeSpace(string $path): ?int
    {
        if (! is_dir($path)) {
            return null;
        }

        $free = @disk_free_space($path);
        return $free === false ? null : (int) $free;
    }

    private function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        foreach ($units as $unit) {
            if ($value < 1024) {
                return sprintf('%.1f %s', $value, $unit);
            }
            $value /= 1024;
        }

        return sprintf('%.1f %s', $value, end($units));
    }

    private function markDegraded(string &$status, int &$httpStatus): void
    {
        if ($status === 'ok') {
            $status = 'degraded';
            $httpStatus = 503;
        }
    }

    private function resolveFailedWindowKey(Request $request): string
    {
        $key = (string) $request->query('failed_window', '1h');
        $allowed = $this->failedWindowMap();

        return array_key_exists($key, $allowed) ? $key : '1h';
    }

    private function failedWindowSeconds(string $key): int
    {
        $map = $this->failedWindowMap();

        return $map[$key] ?? $map['1h'];
    }

    private function failedWindowMap(): array
    {
        return [
            '1h' => 3600,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000,
        ];
    }

    private function resolveHttpTargets(): array
    {
        $envTargets = trim((string) env('HEALTHCHECK_PUBLIC_URLS', ''));
        if ($envTargets !== '') {
            $targets = [];
            foreach (explode(',', $envTargets) as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }
                if (str_contains($entry, '=')) {
                    [$label, $url] = array_map('trim', explode('=', $entry, 2));
                } else {
                    $label = $entry;
                    $url = $entry;
                }
                if ($url !== '') {
                    $targets[$label ?: $url] = $url;
                }
            }

            return $targets;
        }

        if (config('app.env') === 'local') {
            return [];
        }

        if (function_exists('legacy_url')) {
            return [
                'public' => legacy_url(),
                'dev' => legacy_url('dev'),
            ];
        }

        return [];
    }

    private function checkPaths(): array
    {
        $paths = [
            [
                'label' => 'storage_public',
                'path' => storage_path('app/public'),
                'writable' => true,
            ],
            [
                'label' => 'uploads_public',
                'path' => storage_path('app/public/uploads'),
                'writable' => true,
            ],
            [
                'label' => 'uploads_legacy',
                'path' => base_path('../variance/uploads'),
                'writable' => false,
            ],
            [
                'label' => 'variance_data',
                'path' => storage_path('app/private/variance_data'),
                'writable' => true,
            ],
        ];

        $items = [];
        $allOk = true;

        foreach ($paths as $entry) {
            $path = $entry['path'];
            $exists = is_dir($path);
            $writable = $entry['writable'] ? ($exists && is_writable($path)) : ($exists && is_readable($path));
            $ok = $exists && $writable;
            if (! $ok) {
                $allOk = false;
            }
            $items[] = [
                'label' => $entry['label'],
                'path' => $path,
                'exists' => $exists,
                'writable' => $entry['writable'],
                'ok' => $ok,
            ];
        }

        return [
            'ok' => $allOk,
            'items' => $items,
        ];
    }

    private function runMediteProbe(string $mediteUrl): array
    {
        $completedAt = Cache::get('health:medite_probe:completed_at');
        $pending = (bool) Cache::get('health:medite_probe:pending', false);
        $startedAt = Cache::get('health:medite_probe:started_at');

        $age = null;
        if ($completedAt) {
            try {
                $age = now()->diffInSeconds($completedAt, true);
            } catch (\Throwable $e) {
                $age = null;
            }
        }

        $needsProbe = $completedAt === null || ($age !== null && $age > 600);
        $queued = false;

        if ($needsProbe && ! $pending) {
            Cache::put('health:medite_probe:pending', true, 120);
            Cache::put('health:medite_probe:started_at', now()->toIso8601String(), 120);
            $probeUrl = str_contains($mediteUrl, '?') ? $mediteUrl . '&probe=1' : $mediteUrl . '?probe=1';
            try {
                $response = Http::timeout(4)->get($probeUrl);
                if ($response->ok()) {
                    Cache::put('health:medite_probe:completed_at', now()->toIso8601String(), 3600);
                    Cache::forget('health:medite_probe:pending');
                }
            } catch (\Throwable $e) {
                // keep pending flag; next check will retry
            }
            $queued = true;
            $pending = true;
        }

        $ok = $completedAt !== null && ($age === null || $age <= 600);
        $status = $ok ? 'ok' : ($pending ? 'pending' : 'stale');

        return [
            'ok' => $ok,
            'queued' => $queued,
            'pending' => $pending,
            'last_completed_at' => $completedAt,
            'last_started_at' => $startedAt,
            'age_seconds' => $age,
            'status' => $status,
        ];
    }

    private function deriveWorkerStatus(?array $queueCheck): array
    {
        if (! $queueCheck || empty($queueCheck['ok'])) {
            return [
                'ready' => null,
                'status' => 'unknown',
                'note' => 'Queue non disponible',
            ];
        }

        $totals = $queueCheck['totals'] ?? null;
        if (! is_array($totals)) {
            return [
                'ready' => null,
                'status' => 'unknown',
                'note' => 'Statistiques indisponibles',
            ];
        }

        $pending = (int) ($totals['pending'] ?? 0);
        $reserved = (int) ($totals['reserved'] ?? 0);
        $delayed = (int) ($totals['delayed'] ?? 0);
        $stale = (int) ($totals['stale_reserved'] ?? 0);

        if ($stale > 0) {
            return [
                'ready' => false,
                'status' => 'stalled',
                'pending' => $pending,
                'reserved' => $reserved,
                'delayed' => $delayed,
                'stale_reserved' => $stale,
                'note' => 'Jobs réservés bloqués',
            ];
        }

        if ($reserved > 0) {
            return [
                'ready' => true,
                'status' => 'active',
                'pending' => $pending,
                'reserved' => $reserved,
                'delayed' => $delayed,
                'stale_reserved' => $stale,
                'note' => 'Workers actifs',
            ];
        }

        if ($pending > 0) {
            return [
                'ready' => false,
                'status' => 'backlog',
                'pending' => $pending,
                'reserved' => $reserved,
                'delayed' => $delayed,
                'stale_reserved' => $stale,
                'note' => 'Jobs en attente sans worker actif',
            ];
        }

        return [
            'ready' => true,
            'status' => 'idle',
            'pending' => $pending,
            'reserved' => $reserved,
            'delayed' => $delayed,
            'stale_reserved' => $stale,
            'note' => $delayed > 0 ? 'Workers inactifs (jobs différés)' : 'Aucun job en attente',
        ];
    }

    private function runWorkerProbe(string $queueDriver): array
    {
        if ($queueDriver === 'sync') {
            return [
                'ok' => true,
                'status' => 'sync',
                'note' => 'Queue sync',
            ];
        }

        $completedAt = Cache::get('health:probe:completed_at');
        $pending = (bool) Cache::get('health:probe:pending', false);
        $startedAt = Cache::get('health:probe:started_at');

        $age = null;
        if ($completedAt) {
            try {
                $age = now()->diffInSeconds($completedAt, true);
            } catch (\Throwable $e) {
                $age = null;
            }
        }

        $needsProbe = $completedAt === null || ($age !== null && $age > 300);
        $queued = false;

        if ($needsProbe && ! $pending) {
            Cache::put('health:probe:pending', true, 120);
            Cache::put('health:probe:started_at', now()->toIso8601String(), 120);
            HealthcheckProbeJob::dispatch()->onQueue('page-markers');
            $queued = true;
            $pending = true;
        }

        $ok = $completedAt !== null && ($age === null || $age <= 300);

        return [
            'ok' => $ok,
            'queued' => $queued,
            'pending' => $pending,
            'last_completed_at' => $completedAt,
            'last_started_at' => $startedAt,
            'age_seconds' => $age,
            'status' => $ok ? 'ok' : ($pending ? 'pending' : 'stale'),
        ];
    }

    private function readSchedulerHeartbeat(): array
    {
        $path = storage_path('app/private/scheduler_heartbeat.json');
        if (! is_file($path)) {
            $status = config('app.env') === 'local' ? 'disabled' : 'missing';
            return [
                'ok' => $status === 'disabled',
                'status' => $status,
                'path' => $path,
            ];
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [
                'ok' => false,
                'status' => 'unreadable',
                'path' => $path,
            ];
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [
                'ok' => false,
                'status' => 'invalid',
                'path' => $path,
            ];
        }

        $timestamp = $data['timestamp'] ?? null;
        $age = null;
        if (is_string($timestamp)) {
            try {
                $age = now()->diffInSeconds($timestamp, true);
            } catch (\Throwable $e) {
                $age = null;
            }
        }

        $stale = $age !== null && $age > 120;

        return [
            'ok' => ! $stale,
            'status' => $stale ? 'stale' : 'ok',
            'path' => $path,
            'timestamp' => $timestamp,
            'age_seconds' => $age,
        ];
    }

    private function mergeWorkerHeartbeat(array $worker): array
    {
        $path = storage_path('app/private/queue_workers.json');
        if (! is_file($path)) {
            $worker['heartbeat'] = 'missing';
            return $worker;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            $worker['heartbeat'] = 'unreadable';
            return $worker;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $worker['heartbeat'] = 'invalid';
            return $worker;
        }

        $timestamp = $data['timestamp'] ?? null;
        $interval = (int) ($data['interval'] ?? 0);
        $age = null;
        $stale = null;
        if (is_string($timestamp)) {
            try {
                $age = now()->diffInSeconds($timestamp, true);
                $stale = $age > max(60, $interval * 2);
            } catch (\Throwable $e) {
                $age = null;
            }
        }

        $worker['configured'] = $data['count'] ?? null;
        $worker['heartbeat'] = 'ok';
        $worker['heartbeat_at'] = $timestamp;
        $worker['heartbeat_age_seconds'] = $age;
        $worker['heartbeat_stale'] = $stale;

        if ($stale === true && ($worker['pending'] ?? 0) > 0) {
            $worker['ready'] = false;
            $worker['status'] = 'stalled';
            $worker['note'] = 'Heartbeat stale';
        }

        return $worker;
    }
}
