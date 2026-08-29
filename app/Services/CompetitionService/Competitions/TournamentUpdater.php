<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Club;
use App\Models\Game;
use App\Models\Season;
use App\Repositories\Competition\CompetitionStandingsRepository;
use App\Repositories\Competition\CompetitionTournamentRepository;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\GameService\GameService;
use App\Support\GameContext;
use Illuminate\Support\Facades\DB;

class TournamentUpdater
{
    private int $instanceId;

    private Season $season;

    private TournamentConfig $tournamentConfig;

    public function __construct(
        private readonly CompetitionStandingsRepository $standings,
        private readonly CompetitionTournamentRepository $tournaments,
        private readonly GameContext $gameContext,
        ?TournamentConfig $tournamentConfig = null
    ) {
        $this->tournamentConfig = $tournamentConfig ?? new TournamentConfig;
    }

    public function setInstanceId(int $instanceId): void
    {
        $this->instanceId = $instanceId;
        $this->gameContext->setInstanceId($instanceId);
    }

    public function setSeason(Season $season): void
    {
        $this->season = $season;
        $this->tournamentConfig = new TournamentConfig($season->start_date);
        $this->gameContext->setSeasonId($season->id);
    }

    public function updatePointsTable(array $games): void
    {
        DB::transaction(function () use ($games): void {
            foreach ($games as $game) {
                $this->standings->update($game);
            }

            $this->transitionToKnockoutIfFinished($games[0]);
        });
    }

    public function transitionToKnockoutIfFinished(array $game): void
    {
        if (! $this->tournaments->groupsFinished($game)) {
            return;
        }

        $competitionId = $game['competition_id'];

        if (DB::table('tournament_knockout')
            ->where('instance_id', $this->instanceId)
            ->where('competition_id', $competitionId)
            ->where('season_id', $this->season->id)
            ->exists()) {
            $this->markGroupsFinished($competitionId);

            return;
        }

        $knockoutClubs = collect($this->standings->topClubsByGroup($competitionId))
            ->pluck('club_id')->map(fn ($clubId) => (int) $clubId)->all();
        $tournament = new Tournament($this->tournamentConfig);
        $schedule = $tournament->createTournament($knockoutClubs, $this->instanceId, $this->season->id);
        $schedule = $tournament->setTournamentFixtures(
            $this->instanceId,
            $this->season->id,
            $schedule,
            $competitionId,
            $this->tournamentConfig->getWinterKnockoutStartDate()
        );

        (new CompetitionDataSource)->storeTournamentKnockoutSchedule(
            $this->instanceId,
            $competitionId,
            $this->season->id,
            $schedule
        );

        $this->markGroupsFinished($competitionId);
    }

    private function markGroupsFinished(int $competitionId): void
    {
        DB::table('competition_season')
            ->where('instance_id', $this->instanceId)
            ->where('season_id', $this->season->id)
            ->where('competition_id', $competitionId)
            ->update(['groups_active' => false]);
    }

    public function updateTournamentSummary(array $games): void
    {
        $tieIds = collect($games)
            ->pluck('knockout_tie_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($tieIds as $tieId) {
            $this->resolveTie((int) $tieId);
        }
    }

    private function resolveTie(int $tieId): void
    {
        $tie = DB::table('tournament_knockout_ties AS t')
            ->join('tournament_knockout_rounds AS r', 'r.id', '=', 't.round_id')
            ->select('t.*', 'r.number_of_legs', 'r.tournament_knockout_id')
            ->where('t.id', $tieId)
            ->first();

        if (! $tie || $tie->winner_club_id) {
            return;
        }

        $games = Game::query()->where('knockout_tie_id', $tieId)->orderBy('leg_number')->get();
        if ($games->count() !== (int) $tie->number_of_legs || $games->contains(fn (Game $game) => $game->status !== Game::STATUS_COMPLETED || ! $game->winner)) {
            return;
        }

        if ((int) $tie->number_of_legs === 1) {
            $game = $games->first();
            $winnerClubId = (int) $game->winner === 3
                ? (new GameService)->simulateMatchExtraTime($game->id)
                : ((int) $game->winner === 1 ? $game->hometeam_id : $game->awayteam_id);
        } else {
            $winnerClubId = $this->tournaments->roundWinner(
                $games->first()->id,
                $games->last()->id
            );
        }

        if (! $winnerClubId) {
            return;
        }

        DB::transaction(function () use ($tie, $winnerClubId): void {
            DB::table('tournament_knockout_ties')->where('id', $tie->id)->update([
                'winner_club_id' => $winnerClubId,
                'status' => 'completed',
                'updated_at' => now(),
            ]);

            $remainingTies = DB::table('tournament_knockout_ties')
                ->where('round_id', $tie->round_id)
                ->whereNull('winner_club_id')
                ->exists();
            if (! $remainingTies) {
                DB::table('tournament_knockout_rounds')->where('id', $tie->round_id)->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
            }

            if (! $tie->next_tie_id) {
                DB::table('tournament_knockout')->where('id', $tie->tournament_knockout_id)->update([
                    'winner_club_id' => $winnerClubId,
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);

                return;
            }

            $column = $tie->next_tie_slot === 'home' ? 'home_club_id' : 'away_club_id';
            DB::table('tournament_knockout_ties')->where('id', $tie->next_tie_id)->update([
                $column => $winnerClubId,
                'updated_at' => now(),
            ]);
            $this->createTieGamesWhenReady((int) $tie->next_tie_id, (int) $tie->tournament_knockout_id);
        });
    }

    private function createTieGamesWhenReady(int $tieId, int $knockoutId): void
    {
        $tie = DB::table('tournament_knockout_ties AS t')
            ->join('tournament_knockout_rounds AS r', 'r.id', '=', 't.round_id')
            ->select('t.*', 'r.number_of_legs')
            ->where('t.id', $tieId)
            ->lockForUpdate()
            ->first();

        if (! $tie || ! $tie->home_club_id || ! $tie->away_club_id) {
            return;
        }
        if (DB::table('games')->where('knockout_tie_id', $tieId)->exists()) {
            return;
        }

        $knockout = DB::table('tournament_knockout')->where('id', $knockoutId)->first();
        $lastFeederDate = DB::table('tournament_knockout_ties AS feeder')
            ->join('games', 'games.knockout_tie_id', '=', 'feeder.id')
            ->where('feeder.next_tie_id', $tieId)
            ->max('games.match_start');
        $firstDate = $this->tournamentConfig->getNextRoundStartDate(
            $lastFeederDate ?? $this->tournamentConfig->getWinterKnockoutStartDate()
        );
        $homeStadiumId = Club::query()->findOrFail($tie->home_club_id)->stadium_id;
        $awayStadiumId = Club::query()->findOrFail($tie->away_club_id)->stadium_id;

        DB::table('games')->insert([
            'instance_id' => $knockout->instance_id,
            'season_id' => $knockout->season_id,
            'competition_id' => $knockout->competition_id,
            'knockout_tie_id' => $tieId,
            'leg_number' => 1,
            'hometeam_id' => $tie->home_club_id,
            'awayteam_id' => $tie->away_club_id,
            'stadium_id' => $homeStadiumId,
            'match_start' => $firstDate,
        ]);

        if ((int) $tie->number_of_legs === 2) {
            DB::table('games')->insert([
                'instance_id' => $knockout->instance_id,
                'season_id' => $knockout->season_id,
                'competition_id' => $knockout->competition_id,
                'knockout_tie_id' => $tieId,
                'leg_number' => 2,
                'hometeam_id' => $tie->away_club_id,
                'awayteam_id' => $tie->home_club_id,
                'stadium_id' => $awayStadiumId,
                'match_start' => $this->tournamentConfig->getSecondLegDate($firstDate),
            ]);
        }

        DB::table('tournament_knockout_ties')->where('id', $tieId)->update([
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);
        DB::table('tournament_knockout_rounds')->where('id', $tie->round_id)->update([
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);
    }
}
