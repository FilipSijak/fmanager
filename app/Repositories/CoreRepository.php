<?php

namespace App\Repositories;

use App\Repositories\Interfaces\ICoreRepository;

class CoreRepository implements ICoreRepository
{
    protected int|null $seasonId;
    protected int|null $instanceId;

    public function setSeasonId(int|null $seasonId)
    {
        $this->seasonId = $seasonId;
    }

    public function setInstanceId(int|null $instanceId)
    {
        $this->instanceId = $instanceId;
    }
}
