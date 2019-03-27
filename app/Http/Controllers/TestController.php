<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\GameEngine\Player\PlayerCreation;

use App\Player;

class TestController extends Controller
{
    public function index()
    {
    	$playerCreation = new PlayerCreation();
    	$player = $playerCreation->setupPlayer(171);

    	dd($player);
    }
}
