<?php

namespace App\Services\ClubService\SquadAnalysis;

abstract class SquadPlayersConfig
{
    const PLAYER_COUNT          = 36;
    const AVERAGE_PLAYERS_COUNT = 15;
    const BELLOW_AVERAGE_COUNT  = 20;
    const SPECIAL_PLAYERS_COUNT = 5;

    const POSITION_COUNT = [
        'CB'  => 8,
        'LB'  => 3,
        'RB'  => 3,
        'DMC' => 3,
        'CM'  => 5,
        'AMC' => 3,
        'LF'  => 2,
        'RF'  => 2,
        'LW'  => 2,
        'RW'  => 2,
        'ST'  => 3,
    ];
}
