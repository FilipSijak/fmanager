<?php

namespace App\Repositories\Interfaces;

interface ICoreRepository
{
    public function setSeasonId(int $seasonId);

    public function setInstanceId(int $instanceId);
}
