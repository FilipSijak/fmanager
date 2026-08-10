<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\Competition;
use App\Models\CompetitionQualificationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ContinentalQualificationService
{
    public function __construct(private readonly FinalLeagueTableService $tables) {}

    public function calculateForSeason(int $instanceId, int $seasonId, ?int $onlySourceCompetitionId = null): Collection
    {
        $competitions = Competition::query()->forInstance($instanceId)->get();
        $byBaseId = $competitions->keyBy('base_competition_id');
        $rules = CompetitionQualificationRule::query()->where('active', true)
            ->orderBy('priority')->orderBy('id')->get();
        $awardedClubs = [];
        $qualifications = collect();

        foreach ($rules as $rule) {
            $source = $byBaseId->get($rule->source_base_competition_id);
            $target = $byBaseId->get($rule->target_base_competition_id);
            if (! $source || ! $target || ($onlySourceCompetitionId !== null && $source->id !== $onlySourceCompetitionId)) {
                continue;
            }
            if ($target->type !== 'tournament') {
                throw new LogicException('Continental qualification targets must be tournaments.');
            }

            foreach ($this->candidates($rule, $source, $instanceId, $seasonId, $awardedClubs) as $candidate) {
                $clubId = (int) $candidate['club_id'];
                if (isset($awardedClubs[$clubId])) {
                    continue;
                }
                $awardedClubs[$clubId] = true;
                $qualifications->push([
                    'instance_id' => $instanceId,
                    'source_season_id' => $seasonId,
                    'club_id' => $clubId,
                    'source_competition_id' => $source->id,
                    'target_competition_id' => $target->id,
                    'target_base_competition_id' => $target->base_competition_id,
                    'qualification_type' => $rule->selector_type,
                    'source_position' => $candidate['position'],
                    'entry_stage' => $rule->entry_stage,
                    'priority' => $rule->priority,
                    'status' => 'pending',
                ]);
            }
        }

        return $qualifications;
    }

    public function previewForCompetition(Competition $competition, int $seasonId): Collection
    {
        return $this->calculateForSeason($competition->instance_id, $seasonId, $competition->id);
    }

    private function candidates(
        CompetitionQualificationRule $rule,
        Competition $source,
        int $instanceId,
        int $seasonId,
        array $awardedClubs
    ): array {
        if ($rule->selector_type === 'league_position') {
            return $this->leagueCandidates($rule, $source, $instanceId, $seasonId, $awardedClubs);
        }
        if ($rule->selector_type === 'competition_winner') {
            $winner = $this->winner($source, $instanceId, $seasonId);
            if ($winner === null) {
                return [];
            }
            if (isset($awardedClubs[$winner]) && $rule->duplicate_policy === 'next_league_position') {
                return $this->domesticFallback($source, $instanceId, $seasonId, $awardedClubs);
            }

            return [['club_id' => $winner, 'position' => null]];
        }

        throw new LogicException("Unknown qualification selector {$rule->selector_type}.");
    }

    private function leagueCandidates($rule, Competition $source, int $instanceId, int $seasonId, array $awardedClubs): array
    {
        if ($source->type !== 'league' || ! $rule->position_from || ! $rule->position_to || $rule->position_from > $rule->position_to) {
            throw new LogicException('League-position qualification requires a valid league position range.');
        }

        $required = $rule->position_to - $rule->position_from + 1;
        $table = $this->tables->get($instanceId, $seasonId, $source->id);
        $eligibleRows = $rule->duplicate_policy === 'discard'
            ? $table->slice($rule->position_from - 1, $required)
            : $table->slice($rule->position_from - 1);
        $candidates = [];
        foreach ($eligibleRows as $row) {
            if (isset($awardedClubs[(int) $row->club_id])) {
                continue;
            }
            $candidates[] = ['club_id' => (int) $row->club_id, 'position' => (int) $row->position];
            if (count($candidates) === $required) {
                break;
            }
        }

        return $candidates;
    }

    private function winner(Competition $source, int $instanceId, int $seasonId): ?int
    {
        if ($source->type === 'league') {
            return $this->tables->get($instanceId, $seasonId, $source->id)->first()?->club_id;
        }

        return DB::table('tournament_knockout')
            ->where('instance_id', $instanceId)->where('season_id', $seasonId)
            ->where('competition_id', $source->id)->where('status', 'completed')
            ->value('winner_club_id');
    }

    private function domesticFallback(Competition $source, int $instanceId, int $seasonId, array $awardedClubs): array
    {
        $league = Competition::query()->forInstance($instanceId)->where('type', 'league')
            ->where('country_code', $source->country_code)->orderByDesc('rank')->first();
        if (! $league) {
            return [];
        }
        foreach ($this->tables->get($instanceId, $seasonId, $league->id) as $row) {
            if (! isset($awardedClubs[(int) $row->club_id])) {
                return [['club_id' => (int) $row->club_id, 'position' => (int) $row->position]];
            }
        }

        return [];
    }
}
