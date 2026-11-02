<?php

namespace App\Console\Commands;

use App\Models\Workshop;
use App\Services\Reservation\BookingPoints;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('members:award-points')]
#[Description('Give members the points their finished visits earned, and take back those of cancelled ones')]
class AwardMemberPoints extends Command
{
    public function handle(BookingPoints $points): int
    {
        Workshop::whereJsonContains('features', Workshop::FEATURE_MEMBER_PLUS)->each(function (Workshop $workshop) use ($points): void {
            $result = $points->settle($workshop);

            $this->line("{$workshop->name}: {$result['earned']} visits paid out, {$result['reversed']} taken back.");
        });

        return self::SUCCESS;
    }
}
