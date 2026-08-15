<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player as PlayerModel;
use stdClass;

class PlayerType
{
    public function create(stdClass $playerAttributes, int $gameId): PlayerModel
    {
        $player = new PlayerModel;
        $generatedPositions = [];
        $potentialByCategory = [];
        $personIdentity = null;

        foreach ($playerAttributes as $field => $value) {
            if ($field === 'personDetails') {
                $personIdentity = $value;

                continue;
            }

            if (in_array($field, ['first_name', 'last_name', 'dob', 'country_code'], true)) {
                continue;
            }

            if ($field == 'potentialByCategory') {
                $potentialByCategory[$field] = $value;

                continue;
            }

            if ($field == 'playerPositions') {
                foreach ($playerAttributes->playerPositions as $alias => $grade) {
                    $generatedPositions[$alias] = $grade;
                }

                continue;
            }

            $player->{$field} = $value;
        }

        if ($personIdentity instanceof PersonInfo) {
            $player->setPersonIdentity($personIdentity);
        }

        $player->game_id = $gameId;

        $player->setPositions($generatedPositions);
        $player->setAttributesCategoriesPotential($potentialByCategory);

        return $player;
    }
}
