<?php

namespace App\Services\TacticsService;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\ClubTactic;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TacticsService
{
    /**  Collection<int, BaseFormation> */
    public function availableFormations(): Collection
    {
        return BaseFormation::query()
            ->where('is_active', true)
            ->with(['slots' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();
    }

    public function currentForClub(Club $club): ClubTactic
    {
        return DB::transaction(function () use ($club): ClubTactic {
            $tactic = ClubTactic::query()
                ->where('club_id', $club->id)
                ->first();

            if ($tactic === null) {
                $formation = BaseFormation::query()
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();

                if ($formation === null) {
                    throw new DomainException('No active tactics formations are configured.');
                }

                $tactic = ClubTactic::query()->create([
                    'club_id' => $club->id,
                    'base_formation_id' => $formation->id,
                    'name' => 'Default tactic',
                    'mentality' => Mentality::BALANCED,
                    'pressing' => PressingIntensity::MEDIUM,
                    'passing' => PassingStyle::MIXED,
                ]);
            }

            return $tactic->load(['formation.slots']);
        });
    }

    public function saveForClub(
        Club $club,
        int $formationId,
        Mentality $mentality,
        PressingIntensity $pressing,
        PassingStyle $passing,
    ): ClubTactic {
        return DB::transaction(function () use ($club, $formationId, $mentality, $pressing, $passing): ClubTactic {
            $formationExists = BaseFormation::query()
                ->whereKey($formationId)
                ->where('is_active', true)
                ->exists();

            if (! $formationExists) {
                throw new DomainException('The selected tactics formation is not available.');
            }

            $tactic = ClubTactic::query()->updateOrCreate(
                ['club_id' => $club->id],
                [
                    'base_formation_id' => $formationId,
                    'mentality' => $mentality,
                    'pressing' => $pressing,
                    'passing' => $passing,
                ],
            );

            return $tactic->load(['formation.slots']);
        });
    }

    /**  array{mentalities:list<string>,pressing:list<string>,passing:list<string>} */
    public function options(): array
    {
        return [
            'mentalities' => array_map(fn (Mentality $option): string => $option->value, Mentality::cases()),
            'pressing' => array_map(fn (PressingIntensity $option): string => $option->value, PressingIntensity::cases()),
            'passing' => array_map(fn (PassingStyle $option): string => $option->value, PassingStyle::cases()),
        ];
    }
}
