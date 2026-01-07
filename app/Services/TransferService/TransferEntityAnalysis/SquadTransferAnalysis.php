<?php

namespace App\Services\TransferService\TransferEntityAnalysis;

use App\DataModels\PlayerImportance;
use App\Models\Club;
use App\Models\Player;
use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;

class SquadTransferAnalysis
{
    public function isAcceptableTransfer(Club $club, Player $player): PlayerImportance
    {
        $playerImportance = $this->setDefaultPlayerImportance();

        //position deficit equal or higher than
        if (! $this->isAcceptablePositionDeficit($club, $player)) {
            $playerImportance->setPositionDeficit(true);
            $playerImportance->setAcceptableTransfer(false);
        }

        // squad key player
        $clubTopPlayers = $club->players()->keyPlayers()->get();

        foreach ($clubTopPlayers as $ratedPlayer) {
            if ($player->potential > $ratedPlayer->potential) {
                $playerImportance->setKeyPlayer(true);
            }
        }

        // compare player to other players

        return $playerImportance;
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

    private function setDefaultPlayerImportance(): PlayerImportance
    {
        $playerImportance = new PlayerImportance();

        $playerImportance->setKeyPlayer(false);
        $playerImportance->setBestInPosition(false);
        $playerImportance->setAcceptableTransfer(true);
        $playerImportance->setPositionDeficit(false);

        return $playerImportance;
    }
}
