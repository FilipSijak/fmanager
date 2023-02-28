<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player as PlayerModel;
use stdClass;

class PlayerType
{
    /**
     * @param stdClass $playerAttributes
     * @param int      $gameId
     *
     * @return PlayerModel
     */
    public function create(stdClass $playerAttributes, int $gameId): PlayerModel
    {
        $player              = new PlayerModel();
        $generatedPositions  = [];
        $potentialByCategory = [];

        foreach ($playerAttributes as $field => $value) {
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

        $player->game_id = $gameId;

        $player->setPositions($generatedPositions);
        $player->setAttributesCategoriesPotential($potentialByCategory);

        return $player;
    }
}
