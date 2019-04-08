<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\GameEngine\Player\PlayerCreation;
use App\GameEngine\Competition\CreateTournament;
use App\GameEngine\Competition\CreateLeague;

use App\Player;

class TestController extends Controller
{
    public function index()
    {
    	$playerCreation = new PlayerCreation();
        $tournament = new CreateTournament();
        //$tour = $tournament->generate(4);
        $rounds = $tournament->generateRounds(4);
        dd($rounds);
    	//$player = $playerCreation->setupPlayer(171);
    }
}
