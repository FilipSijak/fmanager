<?php

namespace App\Events;

use App\Models\Instance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoardReviewDue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Instance $instance,
        public readonly int $seasonId,
        public readonly CarbonImmutable $reviewDate,
    ) {}
}
