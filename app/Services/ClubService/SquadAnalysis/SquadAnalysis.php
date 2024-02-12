<?php

namespace App\Services\ClubService\SquadAnalysis;

use App\Models\Club;
use App\Services\PersonService\GeneratePeople\PlayerCreateConfig;

class SquadAnalysis
{
    public function optimalNumbersCheckByPosition(Club $club):array
    {
        $players = $club->players()->get();
        $positionCount = PlayerCreateConfig::POSITION_COUNT;
        $positionShortage = [];

        $clubPlayersPositionMapping = [];

        foreach ($players as $player) {
            if (!isset($clubPlayersPositionMapping[$player->position])) {
                $clubPlayersPositionMapping[$player->position] = 0;
            }

            $clubPlayersPositionMapping[$player->position]++;
        }

        foreach ($positionCount as $position => $playerNumbers) {
            $currentClubPositionPlayerNumbers = $clubPlayersPositionMapping[$position];
            if ($currentClubPositionPlayerNumbers < $playerNumbers) {
                $positionShortage[$position] = $currentClubPositionPlayerNumbers - $playerNumbers;
            }
        }

        return $positionShortage;
    }
}
