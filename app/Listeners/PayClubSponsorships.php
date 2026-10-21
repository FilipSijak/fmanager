<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Events\SeasonStarted;
use App\Services\FinanceService\SponsorshipService;

class PayClubSponsorships
{
    public function __construct(private readonly SponsorshipService $sponsorships) {}

    public function handle(SeasonStarted|NextDay $event): void
    {
        $this->sponsorships->payForSeason($event->instance);
    }
}
