<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GameContext;

class CoreController extends Controller
{
    public function __construct(
        private readonly GameContext $gameContext
    ) {}

    protected function seasonId(): int
    {
        return $this->gameContext->seasonId();
    }

    protected function instanceId(): int
    {
        return $this->gameContext->instanceId();
    }
}
