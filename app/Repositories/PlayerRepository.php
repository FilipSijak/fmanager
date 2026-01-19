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
    private PlayerDataSource $playerDataSource;

    public function __construct(PlayerDataSource $playerDataSource)
    {
        $this->playerDataSource = $playerDataSource;
    }

    public function bulkPlayerInsert(
        int $instanceId,
        Club $club = null, /*when creating free players*/
        array $generatedPlayers): void
    {
        foreach ($generatedPlayers as $player) {

            $attributesCategories = $player->getAttributeCategoriesPotential();
            $playerValue = 0;

            if ($club)
            {
                $clubRank = $club->rank * 10;
                $playerValue = $this->calculatePlayerValueWithinClub($player);

                if ($clubRank > $player->potential) {
                    $playerMarketingRank = $player->potential + (($clubRank - $player->potential) / 2);
                } else {
                    $playerMarketingRank = $player->potential - (($player->potential - $clubRank) / 2);
                }
            } else {
                $playerMarketingRank = $player->potential;
            }

            $playerData = [
                'instance_id' => $instanceId,
                'value' => $playerValue,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'marketing_rank' => $playerMarketingRank,
                'potential' => $player->potential,
                'max_potential' => $player->max_potential,
                'ambition' => rand(floor(($player->potentail / 10)), 20),
                'loyalty' => rand(1, 20),
                'position' => $player->position,
                'country_code' => $player->country_code,
                'dob' => $player->dob,
                'technical' => $attributesCategories->technical,
                'mental' => $attributesCategories->mental,
                'physical' => $attributesCategories->physical,
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
            ];

            if ($club) {
                $playerData['club_id'] = $club->id;
            }

            $playerId = DB::table('players')->insertGetId($playerData);

            $contractId = $this->playerDataSource->createContractForGeneratedPlayerByPotential(
                $playerId,
                $instanceId
            );

            Player::where('id', $playerId)->update(['contract_id' => $contractId]);
        }
    }

    /**
     * @param $players
     */
    public function bulkAssignmentPlayersPositions($players): void
    {
        $personService = new PersonService();
        $playerPositionsData = [];

        foreach ($players as $player) {

            $attributes   = $player->getAttributes();
            $positionList = $personService->generatePlayerPositionList($attributes);
            $playerPositions = array_flip(PlayerPositionConfig::PLAYER_POSITIONS);

            foreach ($positionList as $position => $grade) {
                $playerPositionsData[] = [
                    'player_id' => $player->id,
                    'position_id' => $playerPositions[$position],
                    'position_grade' => $grade
                ];
            }
        }

        DB::table('player_position')->insert($playerPositionsData);
    }

    public function calculatePlayerValueWithinClub(Player $player): int
    {
        $currentPotentialValue = $this->valuationByAttribute($player->potential);

        $maxPotentialValue = $this->valuationByAttribute($player->max_potential);
        $marketingRankValue = $this->valuationByAttribute($player->marketing_rank);

        $amount = $currentPotentialValue > $maxPotentialValue ? $currentPotentialValue:
            $maxPotentialValue - (($maxPotentialValue - $currentPotentialValue) / 2);

        $amount = $marketingRankValue > $amount ? $amount + (($maxPotentialValue - $amount) / 2) :
            $amount - (($amount - $maxPotentialValue) /2);

        $amountSize = strlen((string) $amount);

        if ($amountSize <= 6) {
            return round($amount, -3);
        }

        return round($amount, -6);
    }

    private function valuationByAttribute(int $attributeValue) {
        for ($k = 0.1, $i = 10; $i <= 200; $i +=10, $k += 0.06) {
            if ($attributeValue > $i) {
                continue;
            }

            $value = 180 * round(pow($attributeValue, $k), 2) * 1000;
            break;
        }

        return $value;
    }

    public function contractBasedOnPotential(Player $player): array
    {
        return $this->playerDataSource->contractBasedOnPotential($player);
    }
}
