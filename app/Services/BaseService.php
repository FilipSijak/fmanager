<?php

namespace App\Services;

class BaseService
{
    protected int|null $seasonId;
    protected int|null   $instanceId;

    public function setSeasonId(int $seasonId)
    {
        $this->seasonId = $seasonId;
    }

    public function setInstanceId(int $instanceId)
    {
        $this->instanceId = $instanceId;
    }
}
