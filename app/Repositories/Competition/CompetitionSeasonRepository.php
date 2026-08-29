<?php

namespace App\Repositories\Competition;

use App\Models\ClubCompetitionProgression;
use App\Models\Competition;
use App\Models\Season;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CompetitionSeasonRepository
{
    public function __construct(private readonly CompetitionDataSource $dataSource) {}

    /** @return list<int> */
    public function clubIds(int $competitionId, int $seasonId, int $instanceId): array
    {
        return DB::table('competition_season AS cs')
            ->join('clubs', 'clubs.id', '=', 'cs.club_id')
            ->where('cs.competition_id', $competitionId)
            ->where('cs.season_id', $seasonId)
            ->where('cs.instance_id', $instanceId)
            ->where('clubs.instance_id', $instanceId)
            ->orderBy('cs.id')
            ->pluck('cs.club_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function storeInitial(int $instanceId, int $seasonId): void
    {
        $this->dataSource->storeInitialCompetitionSeasonClubs($instanceId, $seasonId);
    }

    public function applyProgressions(int $instanceId, int $sourceSeasonId): Season
    {
        return DB::transaction(function () use ($instanceId, $sourceSeasonId): Season {
            $source = Season::query()->where('instance_id', $instanceId)->lockForUpdate()->findOrFail($sourceSeasonId);
            $start = Carbon::parse($source->start_date)->addYear();
            $next = Season::query()->where('instance_id', $instanceId)->whereDate('start_date', $start)->first();

            if (! $next) {
                $next = new Season;
                $next->instance_id = $instanceId;
                $next->start_date = $start->toDateString();
                $next->end_date = Carbon::create($start->year + 1, 6, 15)->toDateString();
                $next->save();
            }

            $memberships = DB::table('competition_season AS cs')
                ->join('competitions AS competition', 'competition.id', '=', 'cs.competition_id')
                ->where('cs.instance_id', $instanceId)
                ->where('cs.season_id', $sourceSeasonId)
                ->where('competition.instance_id', $instanceId)
                ->where('competition.competition_scope', 'domestic')
                ->select('cs.competition_id', 'cs.club_id')->distinct()->get();

            foreach ($memberships as $membership) {
                $this->storeMembership($instanceId, $next->id, (int) $membership->competition_id, (int) $membership->club_id);
            }

            $progressions = ClubCompetitionProgression::query()
                ->where('instance_id', $instanceId)->where('source_season_id', $sourceSeasonId)
                ->whereIn('status', ['pending', 'applied'])->lockForUpdate()->get();

            foreach ($progressions as $progression) {
                if (in_array($progression->progression_type, ['promotion', 'relegation'], true)) {
                    DB::table('competition_season')->where('instance_id', $instanceId)
                        ->where('season_id', $next->id)->where('competition_id', $progression->source_competition_id)
                        ->where('club_id', $progression->club_id)->delete();
                }

                $this->storeMembership($instanceId, $next->id, (int) $progression->target_competition_id, (int) $progression->club_id);

                if ($progression->status === 'pending') {
                    $progression->forceFill(['status' => 'applied', 'applied_at' => now()])->save();
                }
            }

            return $next;
        });
    }

    private function storeMembership(int $instanceId, int $seasonId, int $competitionId, int $clubId): void
    {
        $groupsActive = Competition::query()->whereKey($competitionId)->where('instance_id', $instanceId)
            ->where('type', 'tournament')->where('groups', 1)->exists();

        DB::table('competition_season')->updateOrInsert(
            ['instance_id' => $instanceId, 'season_id' => $seasonId, 'competition_id' => $competitionId, 'club_id' => $clubId],
            ['group_id' => null, 'groups_active' => $groupsActive, 'points' => 0, 'goals_for' => 0,
                'goals_against' => 0, 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0]
        );
    }
}
