<?php

namespace App\Http\Controllers;

use App\Models\Comparison;
use App\Services\AdminMaintenanceMode;
use Illuminate\Database\Migrations\Migrator;
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
    public function __construct(
        private readonly AdminMaintenanceMode $adminMaintenanceMode,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        [$payload, $httpStatus] = $this->buildReport($this->resolveFailedWindowKey($request));

        return response()->json([
            'status' => ($payload['status'] ?? 'fail') === 'ok' ? 'ok' : 'not_ok',
        ], $httpStatus);
    }

    public function page(Request $request)
    {
        [$payload, $httpStatus] = $this->buildReport($this->resolveFailedWindowKey($request));

        return response()
            ->view('pages.health', $payload)
            ->setStatusCode($httpStatus);
    }

    public function toggleAdminMaintenance(Request $request)
    {
        $enabled = $request->boolean('enabled');

        if ($enabled) {
            $currentState = $this->adminMaintenanceMode->currentState();
            $this->adminMaintenanceMode->activate(
                $currentState['message'] ?? null,
                null,
                (bool) ($currentState['allow_admins'] ?? true),
            );

            return back()->with('status', 'Mode maintenance admin activé.');
        }

        $this->adminMaintenanceMode->deactivate();

        return back()->with('status', 'Mode maintenance admin désactivé.');
    }

    public function updateAdminMaintenanceAnnouncement(Request $request)
    {
        $enabled = $request->boolean('enabled');
        $message = trim((string) $request->input('message', ''));

        if ($enabled) {
            $this->adminMaintenanceMode->announce($message !== '' ? $message : null);

            return back()->with('status', 'Annonce de maintenance enregistrée.');
        }

        $this->adminMaintenanceMode->clearAnnouncement();

        return back()->with('status', 'Annonce de maintenance désactivée.');
    }

    private function buildReport(string $failedWindowKey): array
    {
        $checks = [];
        $status = 'ok';
        $httpStatus = 200;
        $failedWindowSeconds = $this->failedWindowSeconds($failedWindowKey);
        $git = $this->resolveGitMetadata();
        $adminMaintenanceState = $this->adminMaintenanceMode->currentState();
        $adminAnnouncementState = $this->adminMaintenanceMode->currentAnnouncement();

        $checks['app'] = [
            'ok' => true,
            'env' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'git_sha' => $git['sha'],
            'git_sha_short' => $git['short_sha'],
            'git_source' => $git['source'],
        ];
        $checks['config'] = [
            'queue_connection' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'admin_base_path' => config('app.admin_base_path'),
        ];
        $checks['admin_maintenance'] = [
            'ok' => true,
            ...$adminMaintenanceState,
        ];
        $checks['admin_maintenance_announcement'] = [
            'ok' => true,
            ...$adminAnnouncementState,
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
            $this->markCritical($status, $httpStatus);
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
            if ($critBytes !== null && $storageFree <= $critBytes) {
                $diskStatus = 'critical';
            } elseif ($warnBytes !== null && $storageFree <= $warnBytes) {
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
            $this->markCritical($status, $httpStatus);
        }
        if ($diskStatus === 'warning') {
            $this->markWarning($status, $httpStatus);
        }

        $checks['paths'] = $this->checkPaths();
        if (! $checks['paths']['ok']) {
            $this->markCritical($status, $httpStatus);
        }

        $publicPath = public_path();
        $publicOk = is_dir($publicPath) && is_readable($publicPath);
        $checks['public'] = [
            'ok' => $publicOk,
            'path' => $publicPath,
        ];
        if (! $publicOk) {
            $this->markCritical($status, $httpStatus);
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
                $this->markWarning($status, $httpStatus);
            }
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'ok' => false,
                'driver' => $cacheDriver,
                'error' => $e->getMessage(),
            ];
            $this->markWarning($status, $httpStatus);
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
                    foreach (['default', 'facsimiles', 'page-markers', 'exports'] as $queue) {
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
                    $this->markWarning($status, $httpStatus);
                }
            } else {
                $checks['queue'] = [
                    'ok' => false,
                    'driver' => $queueDriver,
                    'error' => 'Redis extension/client not available',
                ];
                $this->markWarning($status, $httpStatus);
            }
        } elseif ($dbOk) {
            $now = now()->timestamp;
            foreach (['default', 'facsimiles', 'page-markers', 'exports'] as $queue) {
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
            $this->markWarning($status, $httpStatus);
        }

        $checks['workers'] = $this->deriveWorkerStatus($checks['queue'] ?? null);
        $checks['workers'] = $this->mergeWorkerHeartbeat($checks['workers']);
        if (in_array(($checks['workers']['status'] ?? null), ['backlog', 'stalled'], true)) {
            $this->markWarning($status, $httpStatus);
        }
        $checks['worker_probe'] = $this->runWorkerProbe($queueDriver);
        if (($checks['worker_probe']['status'] ?? null) === 'stale') {
            $this->markWarning($status, $httpStatus);
        }
        $checks['scheduler'] = $this->readSchedulerHeartbeat();
        if (! ($checks['scheduler']['ok'] ?? false)) {
            $this->markWarning($status, $httpStatus);
        }

        if ($dbOk) {
            try {
                $checks['migrations'] = $this->resolveMigrationStatus(app(Migrator::class));
                if (! ($checks['migrations']['ok'] ?? false)) {
                    $this->markWarning($status, $httpStatus);
                }

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
                $this->markWarning($status, $httpStatus);
            }
        } else {
            $checks['failed_jobs'] = [
                'ok' => false,
                'error' => 'Database unavailable',
            ];
            $this->markWarning($status, $httpStatus);
        }
        $recentFailed = (int) data_get($checks, 'failed_jobs.recent', 0);
        $failedWarnThreshold = (int) env('HEALTHCHECK_FAILED_JOBS_WARN', 1);
        $failedCritThreshold = (int) env('HEALTHCHECK_FAILED_JOBS_CRIT', 10);
        if ($recentFailed >= max($failedCritThreshold, 1)) {
            $this->markCritical($status, $httpStatus);
        } elseif ($recentFailed >= max($failedWarnThreshold, 1)) {
            $this->markWarning($status, $httpStatus);
        }

        $mediteUrl = config('services.medite.health_url') ?? env('MEDITE_HEALTH_URL', 'http://medite:5000/health');
        $mediteWarnMs = (int) env('HEALTHCHECK_MEDITE_WARN_MS', 2500);
        try {
            $mediteStart = microtime(true);
            $response = Http::timeout(2)->get($mediteUrl);
            $mediteLatencyMs = (int) round((microtime(true) - $mediteStart) * 1000);
            $checks['medite'] = [
                'ok' => $response->ok(),
                'status' => $response->status(),
                'latency_ms' => $mediteLatencyMs,
                'url' => $mediteUrl,
                'body' => $response->ok() ? $response->json() : null,
            ];
            if (! $response->ok()) {
                $this->markCritical($status, $httpStatus);
            } elseif ($mediteWarnMs > 0 && $mediteLatencyMs > $mediteWarnMs) {
                $this->markWarning($status, $httpStatus);
            }
        } catch (\Throwable $e) {
            $checks['medite'] = [
                'ok' => false,
                'status' => null,
                'url' => $mediteUrl,
                'error' => $e->getMessage(),
            ];
            $this->markCritical($status, $httpStatus);
        }

        $checks['medite_probe'] = $this->runMediteProbe($mediteUrl);
        if (($checks['medite_probe']['status'] ?? null) === 'stale') {
            $this->markWarning($status, $httpStatus);
        }

        $httpChecks = [];
        $httpTargets = $this->resolveHttpTargets();
        foreach ($httpTargets as $label => $url) {
            try {
                $response = Http::timeout(2)->get($url);
                $ok = $response->status() >= 200 && $response->status() < 400;
                if ($this->isExpectedAdminMaintenanceResponse((string) $label, $response->status(), $adminMaintenanceState)) {
                    $ok = true;
                }
                $httpChecks[$label] = [
                    'ok' => $ok,
                    'status' => $response->status(),
                    'url' => $url,
                ];
                if (! $ok) {
                    if ($this->isCriticalHttpTarget((string) $label)) {
                        $this->markCritical($status, $httpStatus);
                    } else {
                        $this->markWarning($status, $httpStatus);
                    }
                }
            } catch (\Throwable $e) {
                $httpChecks[$label] = [
                    'ok' => false,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ];
                if ($this->isCriticalHttpTarget((string) $label)) {
                    $this->markCritical($status, $httpStatus);
                } else {
                    $this->markWarning($status, $httpStatus);
                }
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
            'timestamp_local' => $timestampLocal->format('d/m/Y H:i'),
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

    private function markWarning(string &$status, int &$httpStatus): void
    {
        if ($status === 'ok') {
            $status = 'degraded';
        }
        $httpStatus = $this->statusToHttpCode($status);
    }

    private function markCritical(string &$status, int &$httpStatus): void
    {
        $status = 'fail';
        $httpStatus = $this->statusToHttpCode($status);
    }

    private function statusToHttpCode(string $status): int
    {
        return $status === 'fail' ? 503 : 200;
    }

    private function isCriticalHttpTarget(string $label): bool
    {
        $normalized = strtolower(trim($label));
        return in_array($normalized, ['public', 'admin', 'health', 'main'], true);
    }

    private function isExpectedAdminMaintenanceResponse(string $label, int $status, array $adminMaintenanceState): bool
    {
        if (! ($adminMaintenanceState['enabled'] ?? false) || $status !== 503) {
            return false;
        }

        return strtolower(trim($label)) === 'admin';
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
                'writable' => true,
            ],
            [
                'label' => 'uploads_images_public',
                'path' => public_path('uploads_images'),
                'writable' => true,
            ],
            [
                'label' => 'uploads_images_legacy',
                'path' => base_path('../variance/uploads_images'),
                'writable' => true,
            ],
            [
                'label' => 'uploads_pdf_legacy',
                'path' => base_path('../variance/uploads/pdf'),
                'writable' => true,
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

    private function resolveMigrationStatus(Migrator $migrator): array
    {
        try {
            if (! $migrator->repositoryExists()) {
                return [
                    'ok' => false,
                    'status' => 'missing_repository',
                    'pending_count' => null,
                    'pending' => [],
                    'ran_count' => 0,
                ];
            }

            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            return [
                'ok' => count($pending) === 0,
                'status' => count($pending) === 0 ? 'ok' : 'pending',
                'pending_count' => count($pending),
                'pending' => $pending,
                'ran_count' => count($ran),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'pending_count' => null,
                'pending' => [],
                'ran_count' => null,
            ];
        }
    }

    private function resolveGitMetadata(): array
    {
        $buildRevision = $this->resolveBuildRevision();
        if ($buildRevision !== null) {
            return [
                'sha' => $buildRevision,
                'short_sha' => substr($buildRevision, 0, 8),
                'source' => 'build',
            ];
        }

        $raw = $this->resolveEnvValue(['APP_GIT_SHA', 'GIT_SHA']);

        $sha = $this->normalizeGitSha($raw);
        if ($sha !== null) {
            return [
                'sha' => $sha,
                'short_sha' => substr($sha, 0, 8),
                'source' => 'env',
            ];
        }

        $sha = $this->resolveGitShaFromWorkTree(base_path());
        if ($sha !== null) {
            return [
                'sha' => $sha,
                'short_sha' => substr($sha, 0, 8),
                'source' => 'git',
            ];
        }

        return [
            'sha' => null,
            'short_sha' => null,
            'source' => 'unknown',
        ];
    }

    private function resolveGitShaFromWorkTree(string $workTree): ?string
    {
        $gitEntry = rtrim($workTree, '/') . '/.git';
        if (is_dir($gitEntry)) {
            return $this->resolveGitShaFromGitDir($gitEntry);
        }

        if (! is_file($gitEntry)) {
            return null;
        }

        $content = @file_get_contents($gitEntry);
        if ($content === false) {
            return null;
        }

        if (! preg_match('/^gitdir:\s*(.+)\s*$/mi', $content, $matches)) {
            return null;
        }

        $gitDir = trim($matches[1]);
        if ($gitDir === '') {
            return null;
        }

        if (! str_starts_with($gitDir, '/')) {
            $gitDir = rtrim($workTree, '/') . '/' . $gitDir;
        }

        return $this->resolveGitShaFromGitDir($gitDir);
    }

    private function resolveBuildRevision(): ?string
    {
        $path = base_path('bootstrap/cache/build_revision.json');
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        foreach (['sha', 'git_sha', 'commit'] as $key) {
            $sha = $this->normalizeGitSha($decoded[$key] ?? null);
            if ($sha !== null) {
                return $sha;
            }
        }

        return null;
    }

    private function resolveGitShaFromGitDir(string $gitDir): ?string
    {
        $headPath = rtrim($gitDir, '/') . '/HEAD';
        if (! is_file($headPath)) {
            return null;
        }

        $head = trim((string) @file_get_contents($headPath));
        if ($head === '') {
            return null;
        }

        if (str_starts_with($head, 'ref:')) {
            $ref = trim(substr($head, 4));
            if ($ref === '') {
                return null;
            }

            $refPath = rtrim($gitDir, '/') . '/' . $ref;
            if (is_file($refPath)) {
                return $this->normalizeGitSha((string) @file_get_contents($refPath));
            }

            $packed = $this->lookupPackedRef($gitDir, $ref);
            if ($packed !== null) {
                return $packed;
            }

            return null;
        }

        return $this->normalizeGitSha($head);
    }

    private function lookupPackedRef(string $gitDir, string $ref): ?string
    {
        $packedRefsPath = rtrim($gitDir, '/') . '/packed-refs';
        if (! is_file($packedRefsPath)) {
            return null;
        }

        $lines = @file($packedRefsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }

            [$sha, $name] = array_pad(preg_split('/\s+/', $line, 2), 2, null);
            if ($name === $ref) {
                return $this->normalizeGitSha($sha);
            }
        }

        return null;
    }

    private function normalizeGitSha(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $sha = strtolower(trim($raw));
        return preg_match('/^[0-9a-f]{7,40}$/', $sha) === 1 ? $sha : null;
    }

    private function resolveEnvValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $_SERVER[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ($keys as $key) {
            $value = getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ($keys as $key) {
            $value = trim((string) env($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $procEnv = @file_get_contents('/proc/1/environ');
        if (is_string($procEnv) && $procEnv !== '') {
            $entries = explode("\0", $procEnv);
            foreach ($entries as $entry) {
                if (! str_contains($entry, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $entry, 2);
                if (in_array($name, $keys, true) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }
}
