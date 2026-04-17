<?php

namespace App\Console\Commands;

use App\Services\AdminMaintenanceMode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AdminMaintenanceAnnounce extends Command
{
    protected $signature = 'admin:maintenance:announce
        {--message= : Announcement message shown on the welcome screen}
        {--starts= : Optional planned start time}
        {--until= : Optional planned end time}';

    protected $description = 'Publish a planned maintenance announcement in the Laravel admin UI.';

    public function handle(AdminMaintenanceMode $maintenanceMode): int
    {
        $startsAt = $this->resolveDate($this->option('starts'));
        $until = $this->resolveDate($this->option('until'));

        if ($this->option('starts') && ! $startsAt) {
            $this->components->error('Impossible de comprendre la date --starts fournie.');

            return self::FAILURE;
        }

        if ($this->option('until') && ! $until) {
            $this->components->error('Impossible de comprendre la date --until fournie.');

            return self::FAILURE;
        }

        $state = $maintenanceMode->announce(
            $this->option('message'),
            $startsAt,
            $until,
        );

        $this->components->info('Annonce de maintenance enregistrée.');
        $this->line('Message : ' . $state['message']);
        $this->line('Début prévu : ' . ($state['starts_at'] ?? 'non précisé'));
        $this->line('Fin prévue : ' . ($state['until'] ?? 'non précisée'));

        return self::SUCCESS;
    }

    private function resolveDate(mixed $value): ?Carbon
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
