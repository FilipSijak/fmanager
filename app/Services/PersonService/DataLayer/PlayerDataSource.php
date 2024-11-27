<?php

namespace App\Services\PersonService\DataLayer;

use App\Models\Player;
use Illuminate\Support\Facades\DB;

class PlayerDataSource
{
    public function createContractForGeneratedPlayerByPotential(
        int $playerId,
    ):void
    {
        $player = Player::where('id', $playerId)->firstOrFail();
        $contract = $this->contractBasedOnPotential($player);

        DB::table('players_contracts')->insert(
            [
                'player_id' => $playerId,
                'salary' => $contract['salary'],
                'appearance' => $contract['appearance'],
                'clean_sheet' => $contract['clean_sheet'],
                'goal' => $contract['goal'],
                'assist' => $contract['assist'],
                'league' => $contract['league'],
                'promotion' => $contract['promotion'],
                'cup' => $contract['cup'],
                'el' => $contract['el'],
                'cl' => $contract['cl'],
                'pc_promotion_salary_raise' => $contract['salary_raise'],
                'pc_demotion_salary_cut' => $contract['demotion'],
            ]
        );
    }

    public function contractBasedOnPotential(
        Player $player
    ): array {
        $salary = 0;
        $appearance = 0;
        $cleanSheet = 0;
        $goal = 0;
        $assist = 0;
        $league = 0;
        $promotion = 0;
        $cup = 0;
        $el = 0;
        $cl = 0;
        $salaryRise = 0;
        $demotion = 0;

        for ($k = 0.1, $i = 10; $i < 210; $i +=10, $k += 0.1) {
            if ($player->potential > $i) {
                continue;
            }

            $salary = (($player->potential * $k * 1000) * ($player->marketing_rank / 100)) / 3;
            $appearance = $player->potential * $k * 50;
            $cleanSheet = $player->position == 'GK' ? $player->potential * $k * 50 : 0;
            $goal = $assist = $appearance;
            $league = $promotion = $salary * 4;
            $cup = $salary * 2;
            $el = $salary * 4;
            $cl = $salary * 6;
            $salaryRise = 0.02;
            $demotion = 0.2;
        }

        return [
            'salary' => $salary,
            'appearance' => $appearance,
            'clean_sheet' => $cleanSheet,
            'goal' => $goal,
            'assist' => $assist,
            'league' => $league,
            'promotion' => $promotion,
            'cup' => $cup,
            'el' => $el,
            'cl' => $cl,
            'salary_raise' => $salaryRise,
            'demotion' => $demotion,
        ];
    }
}
