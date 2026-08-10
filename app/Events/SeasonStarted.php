<?php

namespace App\Events;

use App\Models\Instance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeasonStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Instance $instance) {}
}
