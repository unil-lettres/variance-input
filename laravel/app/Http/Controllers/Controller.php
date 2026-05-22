<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;

abstract class Controller
{
    protected function audit(string $action, array $context = []): void
    {
        app(AuditLogService::class)->record($action, $context, auth()->user());
    }
}
