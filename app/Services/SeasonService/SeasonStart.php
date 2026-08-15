<?php

namespace App\Services\SeasonService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Repositories\StaffRepository;
use App\Services\CompetitionService\CompetitionService;
use App\Services\PersonService\GeneratePeople\StaffType\StaffGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;

class SeasonStart
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly CompetitionService $competitionService,
        private readonly PlayerRetirement $playerRetirement,
        private readonly StaffRetirement $staffRetirement,
        private readonly StaffCountValidator $staffCountValidator,
        private readonly StaffGenerator $staffGenerator,
        private readonly StaffRepository $staffRepository,
    ) {}

    public function process(Instance $instance): void
    {
        $season = Season::query()
            ->where('instance_id', $instance->id)
            ->findOrFail($instance->season_id);

        $retiredPlayers = $this->playerRetirement->retireEligiblePlayers($instance);
        $this->retiredPlayerTransitionToStaff($retiredPlayers);
        $this->staffRetirement->retireEligibleStaff($instance);
        $this->generateMissingStaff($instance);

        Competition::query()
            ->forInstance($instance->id)
            ->orderBy('id')
            ->each(function (Competition $competition) use ($instance, $season): void {
                if ($this->alreadyScheduled($instance->id, $season->id, $competition->id)) {
                    return;
                }

                $clubIds = $this->competitionRepository->clubIdsForCompetitionSeason(
                    $competition->id,
                    $season->id,
                    $instance->id
                );

                if ($clubIds === []) {
                    return;
                }

                if ($competition->type === 'league') {
                    $this->competitionService->makeLeague(
                        $clubIds,
                        $competition->id,
                        $season->id,
                        $instance->id
                    );

                    return;
                }

                if ($competition->type !== 'tournament') {
                    return;
                }

                $clubs = Club::query()
                    ->forInstance($instance->id)
                    ->whereIn('id', $clubIds)
                    ->get()
                    ->sortBy(fn (Club $club): int => array_search($club->id, $clubIds, true))
                    ->values();

                if ((int) $competition->groups === 1) {
                    $this->competitionService->makeTournamentGroupStage(
                        $clubs,
                        $competition->id,
                        $season->id,
                        $instance->id
                    );

                    return;
                }

                $this->competitionService->makeTournament(
                    $clubs,
                    $competition->id,
                    $season->id,
                    $instance->id
                );
            });
    }

    private function alreadyScheduled(int $instanceId, int $seasonId, int $competitionId): bool
    {
        return DB::table('games')
            ->where('instance_id', $instanceId)
            ->where('season_id', $seasonId)
            ->where('competition_id', $competitionId)
            ->exists();
    }

    /** @param list<Player> $retiredPlayers */
    private function retiredPlayerTransitionToStaff(array $retiredPlayers): void
    {
        Collection::make($retiredPlayers)
            ->filter(fn (): bool => Lottery::odds(8, 100)->choose())
            ->each(function ($player): void {
                $person = $player->person;

                $this->staffRepository->insertForExistingPerson(
                    (int) $player->instance_id,
                    (int) $person->id,
                    $this->staffGenerator->generateFromFormerPlayer($person)
                );
            });
    }

    private function generateMissingStaff(Instance $instance): void
    {
        foreach ($this->staffCountValidator->missingStaffByRole($instance) as $role => $missingCount) {
            if ($missingCount === 0) {
                continue;
            }

            $this->staffRepository->bulkStaffInsert(
                (int) $instance->id,
                null,
                $this->staffGenerator->generateFreeStaffForRole($role, $missingCount)
            );
        }
    }
}
