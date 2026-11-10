<?php

namespace App\Services\PersonService\DataLayer;

use App\Models\Instance;
use App\Models\Player;
use App\Services\ContractService\Domain\PlayerContractTerms;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlayerDataSource
{
    public function __construct(private readonly PlayerContractTerms $terms) {}

    const MIN_CONTRACT = 1;

    const MAX_CONTRACT = 6;

    public function createContractForGeneratedPlayerByPotential(
        int $playerId,
        int $instanceId,
    ): int {
        $player = Player::query()->findOrFail($playerId);
        $instanceDate = Instance::query()->findOrFail($instanceId)->instance_date;

        return DB::table('players_contracts')->insertGetId(
            $this->generatedContractData($player, (string) $instanceDate)
        );
    }

    public function generatedContractData(Player $player, string $instanceDate): array
    {
        $contract = $this->terms->forPlayer((int) $player->potential, (int) $player->marketing_rank, $player->position, $player->contract_id !== null);
        $instanceYear = Carbon::parse($instanceDate)->year;
        $contractLeft = rand(self::MIN_CONTRACT, self::MAX_CONTRACT);
        $contractPassed = self::MAX_CONTRACT - $contractLeft;

        return [
            'contract_start' => Carbon::create($instanceYear - $contractPassed, 6, 1),
            'contract_end' => Carbon::create($instanceYear + $contractLeft, 6, 1),
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
        ];
    }
}
