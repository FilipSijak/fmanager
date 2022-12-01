<?php

namespace App\Services\InstanceService;

use App\Services\InstanceService\InstanceData\InitialSeed;

class CreateInstance
{
    public function __construct()
    {

    }

    public function storeGame()
    {
        $init = new InitialSeed();
        $init->seedFromBaseTables(1);
    }
}
