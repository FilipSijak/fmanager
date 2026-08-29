<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\Competition\CompetitionReadRepository;
use App\Repositories\Competition\CompetitionStandingsRepository;
use App\Repositories\Competition\CompetitionTournamentRepository;
use App\Repositories\GameRepository;
use App\Services\CompetitionService\Competitions\KnockoutSummaryRoundsData;
use App\Services\CompetitionService\Competitions\Tournament;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnockoutPersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_and_advances_a_normalized_knockout_bracket(): void
    {
        $instance = Instance::factory()->create(['id' => 1, 'season_id' => 1]);
        $season = Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-15',
        ]);
        $competition = Competition::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'type' => 'tournament',
            'groups' => 0,
        ]);
        $clubs = collect();
        for ($id = 1; $id <= 8; $id++) {
            $clubs->push(Club::factory()->create([
                'id' => $id,
                'instance_id' => $instance->id,
                'stadium_id' => 1000 + $id,
            ]));
        }

        $tournament = new Tournament;
        $schedule = $tournament->createTournament($clubs, $instance->id, $season->id);
        $schedule = $tournament->setTournamentFixtures(
            $instance->id,
            $season->id,
            $schedule,
            $competition->id,
            $season->start_date
        );
        (new CompetitionDataSource)->storeTournamentKnockoutSchedule(
            $instance->id,
            $competition->id,
            $season->id,
            $schedule
        );

        $this->assertDatabaseHas('tournament_knockout', [
            'competition_id' => $competition->id,
            'participant_count' => 8,
            'bracket_size' => 8,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseCount('tournament_knockout_participants', 8);
        $this->assertDatabaseCount('tournament_knockout_rounds', 5);
        $this->assertDatabaseCount('tournament_knockout_ties', 7);
        $this->assertSame(8, DB::table('games')->whereNotNull('knockout_tie_id')->count());

        app(GameContext::class)->set($instance->id, $season->id);
        $summary = app(CompetitionReadRepository::class)->knockoutSummary($competition->id);
        $currentRound = (new KnockoutSummaryRoundsData(app(GameRepository::class)))->getCurrentRound($summary);

        $this->assertCount(2, $currentRound['first_group']);
        $this->assertCount(2, $currentRound['second_group']);
        $this->assertNotNull($currentRound['first_group'][0]['match1Id']);
        $this->assertSame(
            ['quarter_final', 'semi_final', 'final'],
            DB::table('tournament_knockout_rounds')->orderBy('round_number')
                ->orderBy('bracket_side')->pluck('name')->unique()->values()->all()
        );

        app(CompetitionService::class)->makeTournament(
            $clubs,
            $competition->id,
            $season->id,
            $instance->id
        );
        $this->assertSame(8, DB::table('games')->whereNotNull('knockout_tie_id')->count());

        $firstRound = DB::table('tournament_knockout_rounds')
            ->where('bracket_side', 'first')
            ->where('round_number', 1)
            ->first();
        $ties = DB::table('tournament_knockout_ties')
            ->where('round_id', $firstRound->id)
            ->orderBy('position')
            ->get();
        $updater = new TournamentUpdater(
            app(CompetitionStandingsRepository::class),
            app(CompetitionTournamentRepository::class),
            app(GameContext::class),
        );
        $updater->setInstanceId($instance->id);
        $updater->setSeason($season);

        foreach ($ties as $tie) {
            $games = DB::table('games')->where('knockout_tie_id', $tie->id)->orderBy('leg_number')->get();
            DB::table('games')->where('id', $games[0]->id)->update([
                'winner' => 1,
                'status' => 'completed',
                'home_team_goals' => 2,
                'away_team_goals' => 0,
            ]);
            DB::table('games')->where('id', $games[1]->id)->update([
                'winner' => 2,
                'status' => 'completed',
                'home_team_goals' => 0,
                'away_team_goals' => 1,
            ]);
            $updater->updateTournamentSummary(
                DB::table('games')->where('knockout_tie_id', $tie->id)->get()->map(fn ($game) => (array) $game)->all()
            );
        }

        $nextTie = DB::table('tournament_knockout_ties')->where('id', $ties[0]->next_tie_id)->first();
        $this->assertNotNull($nextTie->home_club_id);
        $this->assertNotNull($nextTie->away_club_id);
        $this->assertSame(2, DB::table('games')->where('knockout_tie_id', $nextTie->id)->count());
        $this->assertDatabaseMissing('tournament_knockout_ties', [
            'id' => $ties[0]->id,
            'winner_club_id' => null,
        ]);

        $remainingFirstRoundTies = DB::table('tournament_knockout_ties AS ties')
            ->join('tournament_knockout_rounds AS rounds', 'rounds.id', '=', 'ties.round_id')
            ->where('rounds.round_number', 1)
            ->whereNull('ties.winner_club_id')
            ->pluck('ties.id');
        foreach ($remainingFirstRoundTies as $tieId) {
            $this->completeTie((int) $tieId, $updater);
        }

        $secondRoundTies = DB::table('tournament_knockout_ties AS ties')
            ->join('tournament_knockout_rounds AS rounds', 'rounds.id', '=', 'ties.round_id')
            ->where('rounds.round_number', 2)
            ->whereIn('rounds.bracket_side', ['first', 'second'])
            ->pluck('ties.id');
        foreach ($secondRoundTies as $tieId) {
            $this->completeTie((int) $tieId, $updater);
        }

        $finalTie = DB::table('tournament_knockout_ties AS ties')
            ->join('tournament_knockout_rounds AS rounds', 'rounds.id', '=', 'ties.round_id')
            ->where('rounds.bracket_side', 'final')
            ->select('ties.*')->first();
        $this->completeTie((int) $finalTie->id, $updater);

        $this->assertDatabaseHas('tournament_knockout', [
            'competition_id' => $competition->id,
            'status' => 'completed',
            'winner_club_id' => $finalTie->home_club_id,
        ]);
    }

    private function completeTie(int $tieId, TournamentUpdater $updater): void
    {
        $games = DB::table('games')->where('knockout_tie_id', $tieId)->orderBy('leg_number')->get();
        foreach ($games as $index => $game) {
            DB::table('games')->where('id', $game->id)->update([
                'winner' => $index === 0 ? 1 : 2,
                'status' => 'completed',
                'home_team_goals' => $index === 0 ? 2 : 0,
                'away_team_goals' => $index === 0 ? 0 : 1,
            ]);
        }
        $updater->updateTournamentSummary(
            DB::table('games')->where('knockout_tie_id', $tieId)->get()->map(fn ($game) => (array) $game)->all()
        );
    }
}
