<?php

namespace App\Http\Controllers;

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
