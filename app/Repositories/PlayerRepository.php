<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Player;
use App\Repositories\Interfaces\IPlayerRepository;
use App\Services\PersonService\DataLayer\PlayerDataSource;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\PersonService\PersonService;
use Illuminate\Support\Facades\DB;

class PlayerRepository implements IPlayerRepository
{
    public function bulkPlayerInsert(int $instanceId, Club $club, array $generatedPlayers)
    {
        $playerDataSource = new PlayerDataSource();

        foreach ($generatedPlayers as $player) {

            $attributesCategories = $player->getAttributeCategoriesPotential();
            $playerContractRandomEndingYear =  rand(2024, 2030);
            $contractEndDate = date('Y-m-d', strtotime($playerContractRandomEndingYear . '-06-01'));
            $value = 0;
            $clubRank = $club->rank * 10;

            for ($k = 0.1, $i = 10; $i <= 200; $i +=10, $k += 0.06) {
                if ($player->potential > $i) {
                    continue;
                }

                $value = 180 * round(pow($player->potential, $k), 2) * 1000;
                break;
            }

            if ($clubRank > $player->potential) {
                $playerMarketingRank = $player->potential + (($clubRank - $player->potential) / 2);
            } else {
                $playerMarketingRank = $player->potential - (($player->potential - $clubRank) / 2);
            }

            $playerContractID = $playerDataSource->createContractForGeneratedPlayerByPotential($player->potential, $player->position, $playerMarketingRank);

            DB::table('players')->insert(
                [
                    'instance_id' => $instanceId,
                    'club_id' => $club->id,
                    'player_contract_id' => $playerContractID,
                    'value' => $value,
                    'first_name' => $player->first_name,
                    'last_name' => $player->last_name,
                    'marketing_rank' => $playerMarketingRank,
                    'potential' => $player->potential,
                    'position' => $player->position,
                    'country_code' => $player->country_code,
                    'dob' => $player->dob,
                    'technical' => $attributesCategories->technical,
                    'mental' => $attributesCategories->mental,
                    'physical' => $attributesCategories->physical,
                    'contract_start' =>  date('Y-m-d'),
                    'contract_end' => $contractEndDate,
                    'corners' => $player->corners,
                    'crossing' => $player->crossing,
                    'dribbling' => $player->dribbling,
                    'finishing' => $player->finishing,
                    'first_touch' => $player->first_touch,
                    'freeKick' => $player->freeKick,
                    'heading' => $player->heading,
                    'long_shots' => $player->long_shots,
                    'long_throws' => $player->long_throws,
                    'marking' => $player->marking,
                    'passing' => $player->passing,
                    'penalty_taking' => $player->penalty_taking,
                    'tackling' => $player->tackling,
                    'technique' => $player->technique,
                    'aggression' => $player->aggression,
                    'anticipation' => $player->anticipation,
                    'bravery' => $player->bravery,
                    'composure' => $player->composure,
                    'concentration' => $player->concentration,
                    'creativity' => $player->creativity,
                    'decisions' => $player->decisions,
                    'determination' => $player->determination,
                    'flair' => $player->flair,
                    'leadership' => $player->leadership,
                    'of_the_ball' => $player->of_the_ball,
                    'positioning' => $player->positioning,
                    'teamwork' => $player->teamwork,
                    'workrate' => $player->workrate,
                    'acceleration' => $player->acceleration,
                    'agility' => $player->agility,
                    'balance' => $player->balance,
                    'jumping' => $player->jumping,
                    'natural_fitness' => $player->natural_fitness,
                    'pace' => $player->pace,
                    'stamina' => $player->stamina,
                    'strength' => $player->strength,
                ]
            );
        }
    }

    /**
     * @param $players
     */
    public function bulkAssignmentPlayersPositions($players)
    {
        $personService = new PersonService();

        $insertSql = "INSERT INTO player_position(player_id, position_id, position_grade) VALUES";

        foreach ($players as $player) {

            $attributes   = $player->getAttributes();
            $positionList = $personService->generatePlayerPositionList($attributes);

            $playerPositions = array_flip(PlayerPositionConfig::PLAYER_POSITIONS);

            foreach ($positionList as $position => $grade) {
                $insertSql .= "(" . $player->id . ", '" . $playerPositions[$position] . "', " . $grade . "),";
            }
        }

        $insertSql = substr($insertSql, 0, -1);

        DB::statement($insertSql);
    }
}
