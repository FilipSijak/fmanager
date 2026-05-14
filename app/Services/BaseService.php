<?php

namespace App\Services;

use App\Support\GameContext;

class BaseService
{
    public function setSeasonId(int $seasonId)
    {
        app(GameContext::class)->setSeasonId($seasonId);
    }

    public function setInstanceId(int $instanceId)
    {
        app(GameContext::class)->setInstanceId($instanceId);
    }

    protected function seasonId(): int
    {
        return app(GameContext::class)->seasonId();
    }

    protected function instanceId(): int
    {
        return app(GameContext::class)->instanceId();
    }

    protected function hasSeasonId(): bool
    {
        return app(GameContext::class)->hasSeasonId();
    }

    protected function hasInstanceId(): bool
    {
        return app(GameContext::class)->hasInstanceId();
    }
}
