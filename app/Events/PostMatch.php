<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostMatch
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Game $game) {}
}
