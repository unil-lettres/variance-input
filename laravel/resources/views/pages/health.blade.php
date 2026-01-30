@extends('layouts.app')

@section('title', 'Santé')
@section('body-class', 'admin-health')

@section('content')
@php
    $statusClass = match($status ?? 'ok') {
        'ok' => 'bg-success',
        'degraded' => 'bg-warning text-dark',
        default => 'bg-danger'
    };
    $checks = $checks ?? [];
    $failedWindow = $failed_window ?? data_get($checks, 'failed_jobs.window', '1h');
    $windowOptions = [
        '1h' => '1 h',
        '24h' => '24 h',
        '7d' => '7 j',
        '30d' => '30 j',
    ];
    $okText = 'text-success';
    $warnText = 'text-warning fw-semibold';
    $badText = 'text-danger fw-semibold';
    $mutedText = 'text-muted';
    $statusTone = function (?string $status) use ($okText, $warnText, $badText, $mutedText) {
        return match($status) {
            'ok' => $okText,
            'pending' => $warnText,
            'warning' => $warnText,
            'disabled' => $mutedText,
            'stale', 'critical', 'fail', 'error' => $badText,
            default => $mutedText,
        };
    };
@endphp
<div class="container mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1 d-flex align-items-center gap-2">
                <span>État du système</span>
                <span class="badge {{ $statusClass }} text-uppercase health-status-badge">{{ $status ?? 'ok' }}</span>
            </h1>
            <div class="text-muted small">Dernière mise à jour : {{ $timestamp_local ?? $timestamp ?? '' }}</div>
        </div>
        <div class="d-flex align-items-center gap-3"></div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Site public (legacy)</div>
        <div class="card-body p-0">
            @if(data_get($checks, 'http'))
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Site</th>
                        <th>URL</th>
                        <th>Statut</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(data_get($checks, 'http') as $label => $info)
                        @php
                            $siteOk = (bool) ($info['ok'] ?? false);
                            $siteClass = $siteOk ? $okText : $badText;
                        @endphp
                        <tr class="{{ $siteOk ? '' : 'table-danger' }}">
                            <td>{{ $label }}</td>
                            <td class="text-muted small">{{ $info['url'] ?? '' }}</td>
                            <td class="{{ $siteClass }}">{{ $info['status'] ?? ($info['error'] ?? 'n/a') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-3 text-muted small">Non vérifié en local.</div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Laravel Admin</div>
        <div class="card-body">
            @php
                $cacheOk = data_get($checks, 'cache.ok');
                $cacheClass = $cacheOk === false ? $badText : $mutedText;
                $queueOk = data_get($checks, 'queue.ok');
                $queueClass = $queueOk === false ? $badText : $mutedText;
            @endphp
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Laravel</div>
                    <div>{{ data_get($checks, 'app.laravel') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">PHP</div>
                    <div>{{ data_get($checks, 'app.php') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Environnement</div>
                    <div>{{ data_get($checks, 'app.env') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">URL</div>
                    <div>{{ data_get($checks, 'app.url') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Cache</div>
                    <div class="{{ $cacheClass }}">{{ data_get($checks, 'cache.driver') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Queue</div>
                    <div class="{{ $queueClass }}">{{ data_get($checks, 'queue.driver') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Admin base path</div>
                    <div>{{ data_get($checks, 'config.admin_base_path') ?? '/' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Laravel tasks</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="text-muted small">Workers Laravel</div>
                    @php
                        $worker = data_get($checks, 'workers');
                        $workerStatus = $worker['status'] ?? 'unknown';
                        $workerReady = $worker['ready'];
                        $statusLabel = match($workerStatus) {
                            'active' => 'Actifs',
                            'idle' => 'Prêts (idle)',
                            'backlog' => 'Backlog',
                            'stalled' => 'Bloqués',
                            default => 'Inconnu'
                        };
                        $readyLabel = $workerReady === true ? 'Oui' : ($workerReady === false ? 'Non' : 'n/a');
                        $workerStatusClass = match($workerStatus) {
                            'stalled' => $badText,
                            'backlog' => $warnText,
                            'active', 'idle' => $okText,
                            default => $mutedText
                        };
                        $readyClass = $workerReady === true ? $okText : ($workerReady === false ? $badText : $mutedText);
                        $configured = $worker['configured'] ?? null;
                        $heartbeatAge = $worker['heartbeat_age_seconds'] ?? null;
                        $heartbeatLabel = $heartbeatAge !== null ? number_format($heartbeatAge, 2) . ' s' : 'n/a';
                        $pendingCount = (int) ($worker['pending'] ?? 0);
                        $reservedCount = (int) ($worker['reserved'] ?? 0);
                        $delayedCount = (int) ($worker['delayed'] ?? 0);
                        $pendingClass = $pendingCount > 0 ? $warnText : $mutedText;
                        $reservedClass = $reservedCount > 0 ? $okText : $mutedText;
                        $delayedClass = $delayedCount > 0 ? $warnText : $mutedText;
                        $heartbeatClass = ($worker['heartbeat_stale'] ?? false) ? $badText : $mutedText;
                        $probe = data_get($checks, 'worker_probe');
                        $probeStatus = $probe['status'] ?? 'n/a';
                        $probeAge = $probe['age_seconds'] ?? null;
                        $probeAgeLabel = $probeAge !== null ? number_format($probeAge, 2) . ' s' : 'n/a';
                        $probeClass = $statusTone($probeStatus);
                        $scheduler = data_get($checks, 'scheduler');
                        $schedulerAge = data_get($scheduler, 'age_seconds');
                        $schedulerAgeLabel = $schedulerAge !== null ? number_format($schedulerAge, 2) . ' s' : 'n/a';
                        $schedulerStatus = data_get($scheduler, 'status', 'n/a');
                        $schedulerClass = $statusTone($schedulerStatus);
                    @endphp
                    <div>
                        <span class="{{ $workerStatusClass }}">{{ $statusLabel }}</span>
                        · Disponible : <span class="{{ $readyClass }}">{{ $readyLabel }}</span>
                        @if($configured !== null)
                            · Configuré : {{ $configured }}
                        @endif
                    </div>
                    <div class="text-muted small">
                        <span data-bs-toggle="tooltip" title="Résumé de la situation des workers (file d'attente vide, backlog, bloqué)." class="{{ $workerStatusClass }}">{{ $worker['note'] ?? '' }}</span>
                        @if($worker)
                            · <span data-bs-toggle="tooltip" title="Jobs disponibles en file d'attente." class="{{ $pendingClass }}">En attente: {{ $pendingCount }}</span>
                            · <span data-bs-toggle="tooltip" title="Jobs en cours d'exécution par un worker." class="{{ $reservedClass }}">Réservés: {{ $reservedCount }}</span>
                            · <span data-bs-toggle="tooltip" title="Jobs planifiés pour plus tard (available_at dans le futur)." class="{{ $delayedClass }}">Différés: {{ $delayedCount }}</span>
                            @if($configured !== null)
                                · <span data-bs-toggle="tooltip" title="Âge du dernier heartbeat envoyé par les workers." class="{{ $heartbeatClass }}">Heartbeat: {{ $heartbeatLabel }}</span>
                            @endif
                        @endif
                        · <span data-bs-toggle="tooltip" title="Probe de job Laravel exécuté par la queue." class="{{ $probeClass }}">Probe: {{ $probeStatus }} ({{ $probeAgeLabel }})</span>
                        · <span data-bs-toggle="tooltip" title="Heartbeat du scheduler Laravel (schedule:run)." class="{{ $schedulerClass }}">Scheduler: {{ $schedulerStatus }} ({{ $schedulerAgeLabel }})</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-muted small">Jobs en échec</div>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label class="small text-muted">Fenêtre</label>
                            <select name="failed_window" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach($windowOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($failedWindow === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    @php
                        $recentFailed = data_get($checks, 'failed_jobs.recent');
                        $recentClass = ($recentFailed ?? 0) > 0 ? $badText : $mutedText;
                    @endphp
                    <div class="{{ $recentClass }}">{{ $recentFailed ?? 'n/a' }}</div>
                    <div class="text-muted small">
                        Total: {{ data_get($checks, 'failed_jobs.total') ?? 'n/a' }}
                        · Dernier: {{ data_get($checks, 'failed_jobs.latest_at') ?? 'n/a' }}
                    </div>
                </div>
            </div>
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>Queue</th>
                    <th>En attente</th>
                    <th>Différées</th>
                    <th>Réservées</th>
                    <th>Stales</th>
                    <th>Âge max</th>
                </tr>
                </thead>
                <tbody>
                @foreach(data_get($checks, 'queue.stats', []) as $stat)
                    @php
                        $pending = (int) ($stat['pending'] ?? 0);
                        $delayed = (int) ($stat['delayed'] ?? 0);
                        $reserved = (int) ($stat['reserved'] ?? 0);
                        $stale = (int) ($stat['stale_reserved'] ?? 0);
                        $pendingClass = $pending > 0 ? $warnText : $mutedText;
                        $delayedClass = $delayed > 0 ? $warnText : $mutedText;
                        $reservedClass = $reserved > 0 ? $okText : $mutedText;
                        $staleClass = $stale > 0 ? $badText : $mutedText;
                        $oldestAge = $stat['oldest_pending_age_seconds'] ?? null;
                        $ageClass = $oldestAge !== null && $oldestAge > 600 ? $warnText : $mutedText;
                    @endphp
                    <tr>
                        <td>{{ $stat['queue'] ?? '' }}</td>
                        <td class="{{ $pendingClass }}">{{ $pending }}</td>
                        <td class="{{ $delayedClass }}">{{ $delayed }}</td>
                        <td class="{{ $reservedClass }}">{{ $reserved }}</td>
                        <td class="{{ $staleClass }}">{{ $stale > 0 ? $stale : '-' }}</td>
                        <td class="{{ $ageClass }}">{{ $oldestAge !== null ? $oldestAge . ' s' : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Base de données</div>
        <div class="card-body">
            @php
                $dbOk = (bool) data_get($checks, 'database.ok');
                $dbClass = $dbOk ? $okText : $badText;
                $dbLatency = data_get($checks, 'database.latency_ms');
                $dbLatencyClass = $dbLatency !== null && $dbLatency > 500 ? $warnText : $mutedText;
            @endphp
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Connexion</div>
                    <div class="{{ $dbClass }}">{{ data_get($checks, 'database.connection') }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Latence</div>
                    <div class="{{ $dbLatencyClass }}">{{ $dbLatency !== null ? $dbLatency . ' ms' : 'n/a' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Version</div>
                    <div class="{{ $dbClass }}">{{ data_get($checks, 'database.server_version') ?? 'n/a' }}</div>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-muted small mb-1">Contenu (tables)</div>
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="text-muted small">Users</div>
                        <div>{{ data_get($checks, 'db_counts.users') ?? 'n/a' }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Authors</div>
                        <div>{{ data_get($checks, 'db_counts.authors') ?? 'n/a' }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Works</div>
                        <div>{{ data_get($checks, 'db_counts.works') ?? 'n/a' }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Versions</div>
                        <div>{{ data_get($checks, 'db_counts.versions') ?? 'n/a' }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Comparisons</div>
                        <div>{{ data_get($checks, 'db_counts.comparisons') ?? 'n/a' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Medite</div>
        <div class="card-body">
            @php
                $mediteOk = (bool) data_get($checks, 'medite.ok');
                $mediteStatusClass = $mediteOk ? $okText : $badText;
                $mediteLatency = data_get($checks, 'medite.latency_ms');
                $mediteLatencyClass = $mediteLatency !== null && $mediteLatency > 1000 ? $warnText : $mutedText;
            @endphp
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">URL</div>
                    <div>{{ data_get($checks, 'medite.url') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Statut HTTP</div>
                    <div class="{{ $mediteStatusClass }}">{{ data_get($checks, 'medite.status') ?? 'n/a' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Latence</div>
                    <div class="{{ $mediteLatencyClass }}">{{ $mediteLatency ? $mediteLatency . ' ms' : 'n/a' }}</div>
                </div>
            </div>
            <div class="text-muted small mt-2">
                @php
                    $mediteProbe = data_get($checks, 'medite_probe');
                    $mediteProbeStatus = $mediteProbe['status'] ?? 'n/a';
                    $mediteProbeAge = $mediteProbe['age_seconds'] ?? null;
                    $mediteProbeAgeLabel = $mediteProbeAge !== null ? number_format($mediteProbeAge, 2) . ' s' : 'n/a';
                    $mediteVersions = data_get($checks, 'medite.body.checks.versions', []);
                    $mediteProbeClass = $statusTone($mediteProbeStatus);
                @endphp
                <span class="{{ $mediteProbeClass }}">Probe: {{ $mediteProbeStatus }} ({{ $mediteProbeAgeLabel }})</span>
            </div>
            @if(!empty($mediteVersions))
                <div class="text-muted small mt-2">
                    Versions —
                    Python: {{ $mediteVersions['python'] ?? 'n/a' }},
                    Flask: {{ $mediteVersions['flask'] ?? 'n/a' }},
                    Celery: {{ $mediteVersions['celery'] ?? 'n/a' }},
                    Redis: {{ $mediteVersions['redis_py'] ?? 'n/a' }},
                    Variance: {{ $mediteVersions['variance'] ?? 'n/a' }}
                </div>
            @endif
            @if(data_get($checks, 'medite.body'))
                <div class="mt-3">
                    <pre class="bg-light p-2 small mb-0">{{ json_encode(data_get($checks, 'medite.body'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Infrastructure</div>
        <div class="card-body">
            @php
                $diskStatus = data_get($checks, 'storage.disk_status');
                $diskClass = $statusTone($diskStatus);
                $publicOk = (bool) data_get($checks, 'public.ok', true);
                $publicClass = $publicOk ? $okText : $badText;
            @endphp
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="text-muted small">Storage</div>
                    <div>{{ data_get($checks, 'storage.path') }}</div>
                    <div class="text-muted small">
                        Espace libre : {{ data_get($checks, 'storage.free_human') ?? 'n/a' }}
                        · Seuils : {{ data_get($checks, 'storage.warn_gb') }} / {{ data_get($checks, 'storage.crit_gb') }} Go
                        · Statut : <span class="{{ $diskClass }}">{{ $diskStatus ?? 'n/a' }}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Public</div>
                    <div class="{{ $publicClass }}">{{ data_get($checks, 'public.path') }}</div>
                </div>
            </div>
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>Chemin</th>
                    <th>Existe</th>
                    <th>Mode</th>
                </tr>
                </thead>
                <tbody>
                @foreach(data_get($checks, 'paths.items', []) as $item)
                    @php $pathClass = ($item['ok'] ?? false) ? $okText : $badText; @endphp
                    <tr>
                        <td class="text-muted small">{{ $item['label'] ?? '' }}</td>
                        <td class="{{ $pathClass }}">{{ ($item['ok'] ?? false) ? 'OK' : 'NOK' }}</td>
                        <td>{{ ($item['writable'] ?? false) ? 'Écriture' : 'Lecture' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-muted small">
        Endpoint JSON : <code>{{ admin_path('health') }}</code>
    </div>
</div>
@endsection
