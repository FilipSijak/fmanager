<?php

namespace App\Repositories;

use App\Repositories\Interfaces\ICoreRepository;
use App\Support\GameContext;

class CoreRepository implements ICoreRepository
{
    public function setSeasonId(int|null $seasonId)
    {
        app(GameContext::class)->setSeasonId($seasonId);
    }

    public function setInstanceId(int|null $instanceId)
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
}
