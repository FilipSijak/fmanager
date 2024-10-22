<?php

namespace App\Services\PersonService\DataLayer;

use Illuminate\Support\Facades\DB;

class PlayerDataSource
{
    public function createContractForGeneratedPlayerByPotential(
        int $playerId,
        int $potential,
        string $position,
        int $marketingValue
    ):void
    {
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
            if ($potential > $i) {
                continue;
            }

            $salary = (($potential * $k * 1000) * ($marketingValue / 100)) / 3;
            $appearance = $potential * $k * 50;
            $cleanSheet = $position == 'GK' ? $potential * $k * 50 : NULL;
            $goal = $assist = $appearance;
            $league = $promotion = $salary * 4;
            $cup = $salary * 2;
            $el = $salary * 4;
            $cl = $salary * 6;
            $salaryRise = 0.02;
            $demotion = 0.2;
        }

        DB::table('players_contracts')->insert(
            [
                'player_id' => $playerId,
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
                'pc_salary_raise' => $salaryRise,
                'pc_demotion_pay_cut' => $demotion,
            ]
        );
    }
}
