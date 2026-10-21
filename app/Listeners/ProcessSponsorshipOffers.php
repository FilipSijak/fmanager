<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\FinanceService\SponsorshipOfferService;

class ProcessSponsorshipOffers
{
    public function __construct(private readonly SponsorshipOfferService $offers) {}

    public function handle(NextDay $event): void
    {
        $this->offers->process($event->instance);
    }
}
