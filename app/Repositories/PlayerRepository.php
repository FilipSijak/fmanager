<?php

namespace App\Repositories;

use App\Models\Player;
use App\Repositories\Interfaces\IPlayerRepository;
use App\Services\PersonService\PersonService;
use Illuminate\Support\Facades\DB;

class PlayerRepository implements IPlayerRepository
{
    public function bulkPlayerInsert(int $instanceId, int $clubId, array $generatedPlayers)
    {
        $playerModel = new Player();
        $columns     = $playerModel->getTableColumns();

        unset($columns[0]);

        $playerInsertSQL = "INSERT INTO players(" . implode(", ", $columns) . ") VALUES";

        foreach ($generatedPlayers as $key => $player) {

            $attributesCategories = $player->getAttributeCategoriesPotential();

            $playerInsertSQL      .= "(" . $instanceId . ",
                " . $clubId . ",
                100,
                '" . addslashes($player->first_name) . "',
                '" . addslashes($player->last_name) . "',
                '" . $player->potential . "',
                '" . $player->position . "',
                '" . $player->country_code . "',
                '" . $player->dob . "',
                " . $attributesCategories->technical . ",
                " . $attributesCategories->mental . ",
                " . $attributesCategories->physical . ",
                '" . date('Y-m-d') . "',
                '" . date('Y-m-d') . "',
                " . $player->corners . ",
                " . $player->crossing . ",
                " . $player->dribbling . ",
                " . $player->finishing . ",
                " . $player->first_touch . ",
                " . $player->freeKick . ",
                " . $player->heading . ",
                " . $player->long_shots . ",
                " . $player->long_throws . ",
                " . $player->marking . ",
                " . $player->passing . ",
                " . $player->penalty_taking . ",
                " . $player->tackling . ",
                " . $player->technique . ",
                " . $player->aggression . ",
                " . $player->anticipation . ",
                " . $player->bravery . ",
                " . $player->composure . ",
                " . $player->concentration . ",
                " . $player->creativity . ",
                " . $player->decisions . ",
                " . $player->determination . ",
                " . $player->flair . ",
                " . $player->leadership . ",
                " . $player->of_the_ball . ",
                " . $player->positioning . ",
                " . $player->teamwork . ",
                " . $player->workrate . ",
                " . $player->acceleration . ",
                " . $player->agility . ",
                " . $player->balance . ",
                " . $player->jumping . ",
                " . $player->natural_fitness . ",
                " . $player->pace . ",
                " . $player->stamina . ",
                " . $player->strength . "), ";
        }

        $playerInsertSQL = str_replace(["\r", "\n"], '', $playerInsertSQL);

        $playerInsertSQL = substr($playerInsertSQL, 0, -2);

        DB::statement($playerInsertSQL);
    }

    /**
     * @param $players
     */
    public function bulkAssignmentPlayersPositions($players)
    {
        $personService = new PersonService();

        $insertSql = "INSERT INTO player_position(player_id, position, position_grade) VALUES";

        foreach ($players as $player) {

            $attributes   = $player->getAttributes();
            $positionList = $personService->generatePlayerPositionList($attributes);

            foreach ($positionList as $position => $grade) {
                $insertSql .= "(" . $player->id . ", '" . $position . "', " . $grade . "),";
            }
        }

        $insertSql = substr($insertSql, 0, -1);

        DB::statement($insertSql);
    }
}
