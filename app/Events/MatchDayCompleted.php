<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Instance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MatchDayCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, Game>  $games  the games completed on the instance's current date
     */
    public function __construct(
        public readonly Instance $instance,
        public readonly Collection $games,
    ) {}
}
