<?php

namespace App\Repositories;

use App\DataModels\ClubFinancialSummary;
use App\Models\Club;
use App\Models\Player;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClubRepository
{
    private const WEEKS_PER_YEAR = 52;

    /** @var list<string> */
    private const AVERAGEABLE_PLAYER_ATTRIBUTES = [
        ...PlayerFields::TECHNICAL_FIELDS,
        ...PlayerFields::MENTAL_FIELDS,
        ...PlayerFields::PHYSICAL_FIELDS,
        ...PlayerFields::PERSON_ATTRIBUTE_CATEGORIES,
        'potential', 'max_potential', 'marketing_rank',
    ];

    public function findForInstance(int $clubId, int $instanceId): ?Club
    {
        return Club::query()->with(['stadium', 'account'])->forInstance($instanceId)->find($clubId);
    }

    /** @return Collection<int, Player> */
    public function getSquadByPosition(int $clubId): Collection
    {
        $club = Club::query()->findOrFail($clubId);

        return Player::query()
            ->with(['person', 'contract'])
            ->join('people', 'people.id', '=', 'players.person_id')
            ->select('players.*')
            ->where('players.instance_id', $club->instance_id)
            ->where('people.instance_id', $club->instance_id)
            ->where('players.club_id', $club->id)
            ->where('players.is_retired', false)
            ->orderBy('players.position')
            ->orderBy('people.last_name')
            ->orderBy('players.id')
            ->get();
    }

    /** @return array{player_count:int, average_age:?float, average_potential:?float, total_value:int, weekly_wages:int, contracts_expiring_within_year:int} */
    public function getSquadSummary(int $clubId): array
    {
        $club = Club::query()->findOrFail($clubId);
        $instanceDate = DB::table('instances')->where('id', $club->instance_id)->value('instance_date');

        if ($instanceDate === null) {
            throw new InvalidArgumentException("Club {$clubId} belongs to an instance without a date.");
        }

        $asOfDate = CarbonImmutable::parse($instanceDate);
        $players = DB::table('players AS p')
            ->leftJoin('people AS person', function ($join) use ($club): void {
                $join->on('person.id', '=', 'p.person_id')
                    ->where('person.instance_id', '=', $club->instance_id);
            })
            ->leftJoin('players_contracts AS pc', 'pc.id', '=', 'p.contract_id')
            ->where('p.instance_id', $club->instance_id)
            ->where('p.club_id', $club->id)
            ->where('p.is_retired', false)
            ->select('p.potential', 'p.value', 'person.dob', 'pc.salary', 'pc.contract_end')
            ->get();
        $ages = $players->pluck('dob')->filter()
            ->map(fn (string $dob): int => CarbonImmutable::parse($dob)->diffInYears($asOfDate));

        return [
            'player_count' => $players->count(),
            'average_age' => $ages->isEmpty() ? null : round((float) $ages->average(), 1),
            'average_potential' => $players->isEmpty() ? null : round((float) $players->avg('potential'), 1),
            'total_value' => (int) $players->sum(fn (object $player): int => (int) ($player->value ?? 0)),
            'weekly_wages' => (int) $players->sum(fn (object $player): int => (int) ($player->salary ?? 0)),
            'contracts_expiring_within_year' => $players->filter(function (object $player) use ($asOfDate): bool {
                if ($player->contract_end === null) {
                    return false;
                }

                return CarbonImmutable::parse($player->contract_end)
                    ->betweenIncluded($asOfDate, $asOfDate->addYear());
            })->count(),
        ];
    }

    /** @param list<string> $attributes
     * @return array<string, int|null>
     */
    public function getPositionAttributeAverages(int $clubId, string $position, array $attributes): array
    {
        if ($attributes === []) {
            return [];
        }

        $unknownAttributes = array_diff($attributes, self::AVERAGEABLE_PLAYER_ATTRIBUTES);
        if ($unknownAttributes !== []) {
            throw new InvalidArgumentException('Unknown player attributes: '.implode(', ', $unknownAttributes));
        }

        $club = Club::query()->findOrFail($clubId);
        $columns = collect(array_values(array_unique($attributes)))
            ->map(fn (string $attribute): string => "FLOOR(AVG(`{$attribute}`)) AS `{$attribute}`")
            ->all();
        $averages = DB::table('players')
            ->where('instance_id', $club->instance_id)
            ->where('club_id', $club->id)
            ->where('position', $position)
            ->where('is_retired', false)
            ->selectRaw(implode(', ', $columns))
            ->first();

        return collect((array) $averages)
            ->map(fn ($average): ?int => $average === null ? null : (int) $average)
            ->all();
    }

    /** @deprecated Use getPositionAttributeAverages().
     * @param  list<string>  $attributes
     * @return array<string, int|null>
     */
    public function getAverageAttributesByPosition(int $clubId, string $position, array $attributes): array
    {
        return $this->getPositionAttributeAverages($clubId, $position, $attributes);
    }

    public function getTransferBudgetAndBalance(int $clubId): ?ClubFinancialSummary
    {
        $club = Club::query()->findOrFail($clubId);
        $account = DB::table('accounts')->where('club_id', $club->id)->first();
        if ($account === null) {
            return null;
        }

        $weeklyPlayerWages = (int) DB::table('players AS p')
            ->join('players_contracts AS pc', 'pc.id', '=', 'p.contract_id')
            ->where('p.instance_id', $club->instance_id)
            ->where('p.club_id', $club->id)
            ->where('p.is_retired', false)
            ->sum('pc.salary');
        $annualPlayerWages = $weeklyPlayerWages * self::WEEKS_PER_YEAR;

        return new ClubFinancialSummary(
            balance: (int) $account->balance,
            futureBalance: (int) $account->future_balance,
            allowedDebt: (int) $account->allowed_debt,
            transferBudget: (int) $account->transfer_budget,
            annualSalaryBudget: (int) $account->salaries_yearly_budget,
            annualPlayerWages: $annualPlayerWages,
            remainingAnnualSalaryBudget: (int) $account->salaries_yearly_budget - $annualPlayerWages,
        );
    }
}
