<?php

namespace App\Console\Commands;

use App\Services\AdminMaintenanceMode;
use Illuminate\Console\Command;

class AdminMaintenanceOff extends Command
{
    protected $signature = 'admin:maintenance:off';

    protected $description = 'Disable the Laravel admin maintenance splash mode.';

    public function handle(AdminMaintenanceMode $maintenanceMode): int
    {
        $maintenanceMode->deactivate();

        $this->components->info('Mode maintenance admin désactivé.');

        return self::SUCCESS;
    }
}
