<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\Competition;
use App\Models\CompetitionProgressionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class CompetitionProgressionCalculator
{
    public function __construct(private readonly FinalLeagueTableService $tables) {}

    public function calculateForSeason(int $instanceId, int $seasonId): Collection
    {
        $competitions = Competition::query()->forInstance($instanceId)->get();
        $byBaseId = $competitions->keyBy('base_competition_id');
        $rules = CompetitionProgressionRule::query()->where('active', true)
            ->orderBy('priority')->orderBy('id')->get();
        $continentalAwards = [];
        $progressions = collect();

        foreach ($rules as $rule) {
            $source = $byBaseId->get($rule->source_base_competition_id);
            $target = $byBaseId->get($rule->target_base_competition_id);
            if (! $source || ! $target) {
                continue;
            }

            foreach ($this->candidates($rule, $source, $instanceId, $seasonId, $continentalAwards) as $candidate) {
                $clubId = (int) $candidate['club_id'];
                if ($rule->progression_type === 'continental') {
                    if (isset($continentalAwards[$clubId])) {
                        continue;
                    }
                    $continentalAwards[$clubId] = true;
                }

                $progressions->push([
                    'rule_id' => $rule->id,
                    'instance_id' => $instanceId,
                    'source_season_id' => $seasonId,
                    'club_id' => $clubId,
                    'source_competition_id' => $source->id,
                    'target_competition_id' => $target->id,
                    'progression_type' => $rule->progression_type,
                    'source_position' => $candidate['position'],
                    'entry_stage' => $rule->entry_stage,
                    'status' => 'pending',
                ]);
            }
        }

        return $progressions;
    }

    public function previewForCompetition(
        Competition $competition,
        int $seasonId,
        ?array $progressionTypes = null,
        bool $sourceOnly = false
    ): Collection {
        return $this->calculateForSeason($competition->instance_id, $seasonId)
            ->filter(function (array $progression) use ($competition, $progressionTypes, $sourceOnly): bool {
                $matchesCompetition = $sourceOnly
                    ? $progression['source_competition_id'] === $competition->id
                    : $progression['source_competition_id'] === $competition->id
                        || $progression['target_competition_id'] === $competition->id;

                return $matchesCompetition
                    && ($progressionTypes === null || in_array($progression['progression_type'], $progressionTypes, true));
            })->values();
    }

    private function candidates(
        CompetitionProgressionRule $rule,
        Competition $source,
        int $instanceId,
        int $seasonId,
        array $continentalAwards
    ): array {
        return match ($rule->selector_type) {
            'position_range' => $this->positionCandidates(
                $rule, $source, $instanceId, $seasonId, $continentalAwards
            ),
            'bottom_positions' => $this->bottomCandidates($rule, $source, $instanceId, $seasonId),
            'competition_winner' => $this->winnerCandidates(
                $rule, $source, $instanceId, $seasonId, $continentalAwards
            ),
            default => throw new LogicException("Unknown progression selector {$rule->selector_type}."),
        };
    }

    private function positionCandidates(
        CompetitionProgressionRule $rule,
        Competition $source,
        int $instanceId,
        int $seasonId,
        array $continentalAwards
    ): array {
        if ($source->type !== 'league' || ! $rule->position_from || ! $rule->position_to) {
            throw new LogicException('Position-range progression requires a valid league position range.');
        }

        $required = $rule->position_to - $rule->position_from + 1;
        $table = $this->tables->get($instanceId, $seasonId, $source->id);
        $rows = $rule->progression_type === 'continental' && $rule->duplicate_policy === 'next_league_position'
            ? $table->slice($rule->position_from - 1)
            : $table->slice($rule->position_from - 1, $required);
        $candidates = [];

        foreach ($rows as $row) {
            if ($rule->progression_type === 'continental' && isset($continentalAwards[(int) $row->club_id])) {
                continue;
            }
            $candidates[] = ['club_id' => (int) $row->club_id, 'position' => (int) $row->position];
            if (count($candidates) === $required) {
                break;
            }
        }

        return $candidates;
    }

    private function bottomCandidates(
        CompetitionProgressionRule $rule,
        Competition $source,
        int $instanceId,
        int $seasonId
    ): array {
        if ($source->type !== 'league' || ! $rule->places) {
            throw new LogicException('Bottom-position progression requires a league and number of places.');
        }

        return $this->tables->get($instanceId, $seasonId, $source->id)
            ->take(-(int) $rule->places)->values()
            ->map(fn ($row) => ['club_id' => (int) $row->club_id, 'position' => (int) $row->position])
            ->all();
    }

    private function winnerCandidates(
        CompetitionProgressionRule $rule,
        Competition $source,
        int $instanceId,
        int $seasonId,
        array $continentalAwards
    ): array {
        $winner = $source->type === 'league'
            ? $this->tables->get($instanceId, $seasonId, $source->id)->first()?->club_id
            : DB::table('tournament_knockout')->where('instance_id', $instanceId)
                ->where('season_id', $seasonId)->where('competition_id', $source->id)
                ->where('status', 'completed')->value('winner_club_id');

        if ($winner === null) {
            return [];
        }
        if ($rule->progression_type !== 'continental' || ! isset($continentalAwards[(int) $winner])) {
            return [['club_id' => (int) $winner, 'position' => null]];
        }
        if ($rule->duplicate_policy !== 'next_league_position') {
            return [];
        }

        $league = Competition::query()->forInstance($instanceId)->where('type', 'league')
            ->where('country_code', $source->country_code)->orderByDesc('rank')->first();
        if (! $league) {
            return [];
        }
        foreach ($this->tables->get($instanceId, $seasonId, $league->id) as $row) {
            if (! isset($continentalAwards[(int) $row->club_id])) {
                return [['club_id' => (int) $row->club_id, 'position' => (int) $row->position]];
            }
        }

        return [];
    }
}
