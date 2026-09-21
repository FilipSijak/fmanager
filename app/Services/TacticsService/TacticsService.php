<?php

namespace App\Services\TacticsService;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\ClubTactic;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\PersonService\PersonConfig\PersonTypes;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TacticsService
{
    /** @return Collection<int, BaseFormation> */
    public function availableFormations(): Collection
    {
        return BaseFormation::query()
            ->where('is_active', true)
            ->with(['slots' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();
    }

    public function ensureDefaultForStaff(StaffCoaching $staff): StaffTacticPreference
    {
        return DB::transaction(function () use ($staff): StaffTacticPreference {
            $preference = StaffTacticPreference::query()->where('staff_coaching_id', $staff->id)->first();

            if ($preference !== null) {
                return $preference->load('formation.slots');
            }

            $formation = $this->defaultFormationForStaff($staff);

            return StaffTacticPreference::query()->create([
                'staff_coaching_id' => $staff->id,
                'base_formation_id' => $formation->id,
                'mentality' => Mentality::BALANCED,
                'pressing' => PressingIntensity::MEDIUM,
                'passing' => PassingStyle::MIXED,
            ])->load('formation.slots');
        });
    }

    public function currentForClub(Club $club): ClubTactic
    {
        return DB::transaction(function () use ($club): ClubTactic {
            $manager = $this->managerForClub($club);
            $preference = $this->ensureDefaultForStaff($manager);

            return $this->syncClubTactic($club, $preference);
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
            $formation = BaseFormation::query()
                ->whereKey($formationId)
                ->where('is_active', true)
                ->first();

            if ($formation === null) {
                throw new DomainException('The selected tactics formation is not available.');
            }

            $manager = $this->managerForClub($club);
            $preference = $this->ensureDefaultForStaff($manager);
            $preference->update([
                'base_formation_id' => $formation->id,
                'mentality' => $mentality,
                'pressing' => $pressing,
                'passing' => $passing,
            ]);

            return $this->syncClubTactic($club, $preference->fresh());
        });
    }

    /** @return array{mentalities:list<string>,pressing:list<string>,passing:list<string>} */
    public function options(): array
    {
        return [
            'mentalities' => array_map(fn (Mentality $option): string => $option->value, Mentality::cases()),
            'pressing' => array_map(fn (PressingIntensity $option): string => $option->value, PressingIntensity::cases()),
            'passing' => array_map(fn (PassingStyle $option): string => $option->value, PassingStyle::cases()),
        ];
    }

    private function defaultFormationForStaff(StaffCoaching $staff): BaseFormation
    {
        if ($staff->type === PersonTypes::ASSISTANT_MANAGER && $staff->club_id !== null) {
            $manager = StaffCoaching::query()
                ->where('club_id', $staff->club_id)
                ->where('type', PersonTypes::MANAGER)
                ->active()
                ->first();

            if ($manager !== null && $manager->id !== $staff->id) {
                return $this->ensureDefaultForStaff($manager)->formation;
            }
        }

        $formation = BaseFormation::query()
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if ($formation === null) {
            throw new DomainException('No active tactics formations are configured.');
        }

        return $formation;
    }

    private function managerForClub(Club $club): StaffCoaching
    {
        $manager = StaffCoaching::query()
            ->where('club_id', $club->id)
            ->where('type', PersonTypes::MANAGER)
            ->active()
            ->first();

        if ($manager === null) {
            throw new DomainException('The club does not have an active manager.');
        }

        return $manager;
    }

    private function syncClubTactic(Club $club, StaffTacticPreference $preference): ClubTactic
    {
        $tactic = ClubTactic::query()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'base_formation_id' => $preference->base_formation_id,
                'name' => 'Manager tactic',
                'mentality' => $preference->mentality,
                'pressing' => $preference->pressing,
                'passing' => $preference->passing,
            ],
        );

        $this->alignAssistantManagerFormation($club, $preference->base_formation_id);

        return $tactic->load(['formation.slots']);
    }

    private function alignAssistantManagerFormation(Club $club, int $formationId): void
    {
        $assistants = StaffCoaching::query()
            ->where('club_id', $club->id)
            ->where('type', PersonTypes::ASSISTANT_MANAGER)
            ->active()
            ->get();

        foreach ($assistants as $assistant) {
            $preference = $this->ensureDefaultForStaff($assistant);

            if ($preference->base_formation_id !== $formationId) {
                $preference->update(['base_formation_id' => $formationId]);
            }
        }
    }
}
