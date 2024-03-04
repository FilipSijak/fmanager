<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerResource;
use App\Models\Player;

class PlayerController extends CoreController
{
    public function show(int $playerId)
    {
        $player = Player::where('instance_id', $this->instanceId)
                        ->where('id', $playerId)
                        ->first();

        return new PlayerResource($player);
    }
}
