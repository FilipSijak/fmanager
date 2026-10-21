<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Events\SeasonStarted;
use App\Models\Season;
use App\Services\FinanceService\SponsorshipService;
use Carbon\CarbonImmutable;

class PayClubSponsorships
{
    public function __construct(private readonly SponsorshipService $sponsorships) {}

    public function handle(SeasonStarted|NextDay $event): void
    {
        if ($event instanceof NextDay) {
            $isSeasonStart = Season::query()->where('instance_id', $event->instance->id)
                ->whereKey($event->instance->season_id)
                ->whereDate('start_date', CarbonImmutable::parse($event->instance->instance_date)->toDateString())
                ->exists();
            if (! $isSeasonStart) {
                return;
            }
        }

        $this->sponsorships->payForSeason($event->instance);
    }
}
