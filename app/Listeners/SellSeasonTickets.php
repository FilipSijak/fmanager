<?php

namespace App\Listeners;

use App\Events\SeasonStarted;
use App\Services\SeasonTicketService\SeasonTicketService;

class SellSeasonTickets
{
    public function __construct(private readonly SeasonTicketService $seasonTickets) {}

    public function handle(SeasonStarted $event): void
    {
        $this->seasonTickets->sellForSeason($event->instance);
    }
}
