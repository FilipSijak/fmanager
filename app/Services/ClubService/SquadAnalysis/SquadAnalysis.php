<?php

namespace App\Services\ClubService\SquadAnalysis;

use App\Models\Club;
use App\Models\Player;

class SquadAnalysis
{
    public function internalSquadAnalysis(Club $club)
    {
        return $this->optimalNumbersCheckByPosition($club);
    }

    public function isAcceptableTransfer(Club $club, Player $player): bool
    {
        //position deficit equal or higher than
        if (! $this->isAcceptablePositionDeficit($club, $player)) {
            return false;
        }

        // squad key player
        // compare player to other players

        return true;
    }

    public function isAcceptablePositionDeficit(Club $club, Player $player)
    {
        $squadPositionsNumbers = $this->optimalNumbersCheckByPosition($club);

        if (empty($squadPositionsNumbers) || !isset($squadPositionsNumbers[$player->position])) {
            return true;
        }

        return SquadPlayersConfig::POSITION_COUNT[$player->position] + ($squadPositionsNumbers[$player->position]) >=
            SquadPlayersConfig::MIN_PLAYER_COUNT_BY_POSITION[$player->position];
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
