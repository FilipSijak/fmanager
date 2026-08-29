<?php

namespace App\Repositories\Competition;

use App\Models\Game;
use App\Services\GameService\GameService;
use App\Support\GameContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CompetitionTournamentRepository
{
    public function __construct(private readonly GameContext $context, private readonly GameService $gameService) {}

    public function knockoutSummary(int $competitionId): string
    {
        $knockout = DB::table('tournament_knockout')->where('instance_id', $this->context->instanceId())
            ->where('season_id', $this->context->seasonId())->where('competition_id', $competitionId)->first();
        if (! $knockout) {
            return '';
        }

        $rounds = DB::table('tournament_knockout_rounds')->where('tournament_knockout_id', $knockout->id)
            ->orderBy('round_number')->get();
        $ties = DB::table('tournament_knockout_ties')->whereIn('round_id', $rounds->pluck('id'))
            ->orderBy('position')->get();
        $games = DB::table('games')->where('instance_id', $this->context->instanceId())
            ->where('season_id', $this->context->seasonId())->whereIn('knockout_tie_id', $ties->pluck('id'))
            ->orderBy('leg_number')->get();
        $tiesByRound = $ties->groupBy('round_id');
        $gamesByTie = $games->groupBy('knockout_tie_id');
        $summary = [
            'id' => $knockout->id, 'instance_id' => $knockout->instance_id,
            'season_id' => $knockout->season_id, 'competition_id' => $knockout->competition_id,
            'participant_count' => $knockout->participant_count, 'bracket_size' => $knockout->bracket_size,
            'status' => $knockout->status, 'winner' => $knockout->winner_club_id, 'finals_match' => null,
            'first_group' => ['num_rounds' => 0, 'rounds' => []],
            'second_group' => ['num_rounds' => 0, 'rounds' => []],
        ];

        foreach (['first' => 'first_group', 'second' => 'second_group'] as $side => $key) {
            $sideRounds = $rounds->where('bracket_side', $side);
            $summary[$key]['num_rounds'] = $sideRounds->count();
            foreach ($sideRounds as $round) {
                $summary[$key]['rounds'][$round->round_number] = [
                    'id' => $round->id, 'name' => $round->name, 'status' => $round->status,
                    'pairs' => $this->roundPairs($tiesByRound->get($round->id, collect()), $gamesByTie),
                ];
            }
        }

        $finalRound = $rounds->firstWhere('bracket_side', 'final');
        if ($finalRound && ($finalTie = $tiesByRound->get($finalRound->id, collect())->first())) {
            $summary['finals_match'] = $gamesByTie->get($finalTie->id, collect())->first()?->id;
        }

        return json_encode($summary, JSON_THROW_ON_ERROR);
    }

    public function groupsFinished(array $match): bool
    {
        return ! DB::table('games')->where('competition_id', $match['competition_id'])
            ->where('season_id', $match['season_id'])->where('instance_id', $match['instance_id'])
            ->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_IN_PROGRESS, Game::STATUS_POSTPONED])->exists();
    }

    public function disableGroups(int $competitionId): void
    {
        DB::table('competitions')->where('id', $competitionId)
            ->where('instance_id', $this->context->instanceId())->update(['groups' => 0]);
    }

    public function roundWinner(int $firstId, int $secondId): ?int
    {
        $games = Game::query()->where('instance_id', $this->context->instanceId())
            ->where('season_id', $this->context->seasonId())->whereIn('id', [$firstId, $secondId])->get()->keyBy('id');
        $first = $games->get($firstId);
        $second = $games->get($secondId);
        if (! $first || ! $second || ! $second->winner) {
            return null;
        }
        if ((int) $first->hometeam_id !== (int) $second->awayteam_id
            || (int) $first->awayteam_id !== (int) $second->hometeam_id) {
            throw new LogicException('Knockout legs do not contain the same clubs in reverse order.');
        }

        $home = (int) $first->home_team_goals + (int) $second->away_team_goals;
        $away = (int) $first->away_team_goals + (int) $second->home_team_goals;

        return match (true) {
            $home > $away => (int) $first->hometeam_id,
            $away > $home => (int) $first->awayteam_id,
            default => $this->gameService->simulateMatchExtraTime($second->id),
        };
    }

    private function roundPairs(Collection $ties, Collection $gamesByTie): array
    {
        return $ties->map(function ($tie) use ($gamesByTie): array {
            $games = $gamesByTie->get($tie->id, collect());
            $first = $games->get(0);
            $second = $games->get(1);

            return [
                'id' => $tie->id,
                'match1' => ['homeTeamId' => $first->hometeam_id ?? $tie->home_club_id,
                    'awayTeamId' => $first->awayteam_id ?? $tie->away_club_id],
                'match2' => ['homeTeamId' => $second->hometeam_id ?? $tie->away_club_id,
                    'awayTeamId' => $second->awayteam_id ?? $tie->home_club_id],
                'winner' => $tie->winner_club_id, 'status' => $tie->status,
                'match1Id' => $first->id ?? null, 'match2Id' => $second->id ?? null,
            ];
        })->all();
    }
}
