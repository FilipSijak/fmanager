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
        $personIdentity = [];

        foreach ($playerAttributes as $field => $value) {
            if (in_array($field, ['first_name', 'last_name', 'dob', 'country_code'], true)) {
                $personIdentity[$field] = $value;

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

        $player->setPersonIdentity($personIdentity);

        $player->game_id = $gameId;

        $player->setPositions($generatedPositions);
        $player->setAttributesCategoriesPotential($potentialByCategory);

        return $player;
    }
}
