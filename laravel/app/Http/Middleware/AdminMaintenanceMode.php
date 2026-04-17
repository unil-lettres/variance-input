<?php

namespace App\Http\Middleware;

use App\Services\AdminMaintenanceMode as AdminMaintenanceState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMaintenanceMode
{
    public function __construct(
        private readonly AdminMaintenanceState $maintenanceMode,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExcluded($request) || ! $this->maintenanceMode->isEnabled()) {
            return $next($request);
        }

        if ($this->maintenanceMode->shouldBypassFor($request->user())) {
            return $next($request);
        }

        $payload = $this->maintenanceMode->publicPayload();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()
                ->json($payload, 503)
                ->header('Retry-After', $this->retryAfterHeader($payload['until'] ?? null));
        }

        return response()
            ->view('pages.admin_maintenance', [
                'maintenanceState' => $this->maintenanceMode->currentState(),
            ])
            ->setStatusCode(503)
            ->header('Retry-After', $this->retryAfterHeader($payload['until'] ?? null));
    }

    private function isExcluded(Request $request): bool
    {
        return $request->is('health')
            || $request->is('up')
            || $request->routeIs('maintenance.notice');
    }

    private function retryAfterHeader(?string $until): string
    {
        if (! is_string($until) || trim($until) === '') {
            return '300';
        }

        try {
            $seconds = now()->diffInSeconds($until, false);

            return (string) max(60, $seconds);
        } catch (\Throwable) {
            return '300';
        }
    }
}
