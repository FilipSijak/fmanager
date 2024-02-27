<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerResource;
use App\Models\Player;

class PlayerController extends Controller
{
    public function show(Player $player)
    {
        return new PlayerResource($player);
    }
}
