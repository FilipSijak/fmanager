<?php

namespace App\Listeners;

use App\Events\PostMatch;
use App\Services\MatchdayService\MatchdayService;

class SettleMatchdayRevenue
{
    public function __construct(private readonly MatchdayService $matchdays) {}

    public function handle(PostMatch $event): void
    {
        $this->matchdays->settle($event->game);
    }
}
