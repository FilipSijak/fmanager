<?php

namespace App\Services;

class BaseService
{
    protected int $seasonId;
    private int   $instanceId;

    public function setSeasonId(int $seasonId)
    {
        $this->seasonId = $seasonId;
    }

    public function setInstanceId(int $instanceId)
    {
        $this->instanceId = $instanceId;
    }
}
