<?php

namespace App\Console\Commands;

use App\Services\AdminMaintenanceMode;
use Illuminate\Console\Command;

class AdminMaintenanceAnnouncementClear extends Command
{
    protected $signature = 'admin:maintenance:announce:clear';

    protected $description = 'Clear the planned maintenance announcement from the Laravel admin UI.';

    public function handle(AdminMaintenanceMode $maintenanceMode): int
    {
        $maintenanceMode->clearAnnouncement();

        $this->components->info('Annonce de maintenance supprimée.');

        return self::SUCCESS;
    }
}
