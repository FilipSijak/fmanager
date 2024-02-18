<?php

namespace App\Services\ClubService\SquadAnalysis;

use App\Models\Club;
use App\Models\Player;
use App\Services\PersonService\GeneratePeople\PlayerCreateConfig;

class SquadAnalysis
{
    public function internalSquadAnalysis(Club $club)
    {
        return $this->optimalNumbersCheckByPosition($club);
    }

    public function isAcceptableTransfer(Club $club, Player $player): bool
    {
        //position deficit equal or higher than
        $this->isAcceptablePositionDeficit();

        return true;
    }

    public function isAcceptablePositionDeficit()
    {

    }

    public function optimalNumbersCheckByPosition(Club $club):array
    {
        $players = $club->players()->get();

        $positionCount = SquadPlayersConfig::POSITION_COUNT;
        $positionShortage = [];

        $clubPlayersPositionMapping = [];

        foreach ($players as $player) {
            if (!isset($clubPlayersPositionMapping[$player->position])) {
                $clubPlayersPositionMapping[$player->position] = 0;
            }

            $clubPlayersPositionMapping[$player->position]++;
        }

        foreach ($positionCount as $position => $clubDefinedPLayerNumbers) {
            if (!isset($clubPlayersPositionMapping[$position])) {
                $positionShortage[$position] = -$clubDefinedPLayerNumbers;

                continue;
            }

            $currentClubPositionPlayerNumbers = $clubPlayersPositionMapping[$position];

            if ($currentClubPositionPlayerNumbers < $clubDefinedPLayerNumbers) {
                $positionShortage[$position] = $currentClubPositionPlayerNumbers - $clubDefinedPLayerNumbers;
            }
        }

        return $positionShortage;
    }
}
