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

    const MIN_PLAYER_COUNT_BY_POSITION = [
        'CB'  => 5,
        'LB'  => 1,
        'RB'  => 1,
        'DMC' => 2,
        'CM'  => 2,
        'AMC' => 2,
        'LF'  => 1,
        'RF'  => 1,
        'LW'  => 1,
        'RW'  => 1,
        'ST'  => 2,
    ];
}
