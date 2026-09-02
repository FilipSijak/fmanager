<?php

namespace App\Listeners;

use App\Events\SeasonStarted;
use App\Services\SeasonService\UpdatePlayerPotentials;

class UpdatePlayerPotentialsOnSeasonStart
{
    public function __construct(
        private readonly UpdatePlayerPotentials $updatePlayerPotentials
    ) {}

    public function handle(SeasonStarted $event): void
    {
        $this->updatePlayerPotentials->process($event->instance);
    }
}
