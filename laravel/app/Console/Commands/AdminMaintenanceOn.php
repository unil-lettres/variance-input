<?php

namespace App\Console\Commands;

use App\Services\AdminMaintenanceMode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AdminMaintenanceOn extends Command
{
    protected $signature = 'admin:maintenance:on
        {--message= : Splash message shown to non-admin users}
        {--until= : Optional planned end time}
        {--no-admin-bypass : Also block already-authenticated admins}';

    protected $description = 'Enable the Laravel admin maintenance splash mode.';

    public function handle(AdminMaintenanceMode $maintenanceMode): int
    {
        $until = $this->resolveUntil($this->option('until'));

        if ($this->option('until') && ! $until) {
            $this->components->error('Impossible de comprendre la date --until fournie.');

            return self::FAILURE;
        }

        $state = $maintenanceMode->activate(
            $this->option('message'),
            $until,
            ! (bool) $this->option('no-admin-bypass'),
        );

        $this->components->info('Mode maintenance admin activé.');
        $this->line('Message : ' . $state['message']);
        $this->line('Admins connectés autorisés : ' . ($state['allow_admins'] ? 'oui' : 'non'));
        $this->line('Fin prévue : ' . ($state['until'] ?? 'non précisée'));

        return self::SUCCESS;
    }

    private function resolveUntil(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
