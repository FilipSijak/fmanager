<?php

namespace App\Repositories;

use App\Repositories\Interfaces\ICoreRepository;

class CoreRepository implements ICoreRepository
{
    protected int $seasonId;
    protected int $instanceId;

    public function setSeasonId(int $seasonId)
    {
        $this->seasonId = $seasonId;
    }

    public function setInstanceId(int $instanceId)
    {
        $this->instanceId = $instanceId;
    }
}
