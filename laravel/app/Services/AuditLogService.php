<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    public function record(string $action, array $context = [], ?Authenticatable $user = null): void
    {
        $request = request();

        Log::info("audit.{$action}", [
            'event' => 'audit',
            'action' => $action,
            'actor_id' => $user?->getAuthIdentifier(),
            'actor_name' => $user?->name ?? null,
            'actor_is_admin' => is_object($user) && property_exists($user, 'is_admin') ? (bool) $user->is_admin : null,
            'route' => $request?->path(),
            'method' => $request?->method(),
            'ip' => $request?->ip(),
            ...$context,
        ]);
    }
}
