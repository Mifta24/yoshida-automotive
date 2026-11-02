<?php

namespace App\Console\Commands;

use App\Models\ServiceType;
use App\Services\Reservation\InventoryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:extend {--days=365 : How many days ahead services should be open for sale}')]
#[Description('Open further days for every service type, repeating the services and prices of the week before')]
class ExtendInventory extends Command
{
    public function handle(InventoryService $inventory): int
    {
        $days = max(1, (int) $this->option('days'));
        $added = 0;

        ServiceType::where('is_active', true)->each(function (ServiceType $serviceType) use ($inventory, $days, &$added): void {
            $added += $inventory->extend($serviceType, $days);
        });

        $this->info("Opened {$added} day(s).");

        return self::SUCCESS;
    }
}
