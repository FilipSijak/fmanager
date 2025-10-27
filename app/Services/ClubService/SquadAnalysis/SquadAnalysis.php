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

    public function isAcceptableTransfer(Club $club, Player $player): \stdClass
    {
        $response = new \stdClass();
        $response->key_player = false;
        $response->best_in_position = false;
        $response->acceptable_transfer = true;
        $response->position_deficit = false;

        //position deficit equal or higher than
        if (! $this->isAcceptablePositionDeficit($club, $player)) {
            $response->position_deficit = true;
            $response->acceptable_transfer = false;
        }

        // squad key player
        $clubTopPlayers = $club->players()->keyPlayers()->get();

        foreach ($clubTopPlayers as $ratedPlayer) {
            if ($player->potential > $ratedPlayer->potential) {
                $response->key_player = true;
            }
        }


        // compare player to other players

        return $response;
    }

    public function isAcceptablePositionDeficit(Club $club, Player $player): bool
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
