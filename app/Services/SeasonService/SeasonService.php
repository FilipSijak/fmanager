<?php

namespace App\Services\SeasonService;

use App\Models\Instance;

class SeasonService
{
    public function __construct(
        private readonly SeasonStart $seasonStart,
        private readonly SeasonCompletion $seasonCompletion
    ) {}

    public function start(Instance $instance): void
    {
        $this->seasonStart->process($instance);
    }

    public function complete(Instance $instance): void
    {
        $this->seasonCompletion->process($instance);
    }
}
