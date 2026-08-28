<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Competitions\CompetitionUpdater;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\GameService\GameService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupToKnockoutTransitionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_transitions_to_knockout_and_routes_generated_games_to_tie_progression(): void
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
            'groups' => 1,
            'clubs_number' => 4,
        ]);

        $clubs = collect();
        for ($id = 1; $id <= 4; $id++) {
            $clubs->push(Club::factory()->create([
                'id' => $id,
                'instance_id' => $instance->id,
                'stadium_id' => 1000 + $id,
            ]));
        }

        DB::table('competition_season')->insert([
            $this->groupRow($competition->id, 1, $clubs[0]->id, 6),
            $this->groupRow($competition->id, 1, $clubs[1]->id, 3),
            $this->groupRow($competition->id, 2, $clubs[2]->id, 6),
            $this->groupRow($competition->id, 2, $clubs[3]->id, 3),
        ]);

        $groupGames = collect([
            Game::factory()->create([
                'instance_id' => 1,
                'season_id' => 1,
                'competition_id' => $competition->id,
                'hometeam_id' => $clubs[0]->id,
                'awayteam_id' => $clubs[1]->id,
                'stadium_id' => $clubs[0]->stadium_id,
                'winner' => 1,
                'status' => 'completed',
                'home_team_goals' => 2,
                'away_team_goals' => 0,
            ]),
            Game::factory()->create([
                'instance_id' => 1,
                'season_id' => 1,
                'competition_id' => $competition->id,
                'hometeam_id' => $clubs[2]->id,
                'awayteam_id' => $clubs[3]->id,
                'stadium_id' => $clubs[2]->stadium_id,
                'winner' => 1,
                'status' => 'completed',
                'home_team_goals' => 1,
                'away_team_goals' => 0,
            ]),
        ]);

        $updater = $this->competitionUpdater();
        $updater->setGamesByCompetition([
            $competition->id => $groupGames->map(fn (Game $game) => $game->toArray())->all(),
        ]);
        $updater->distributeGamesForCompetitionUpdates($season, $instance->id);

        $this->assertDatabaseHas('competitions', ['id' => $competition->id, 'groups' => 1]);
        $this->assertDatabaseMissing('competition_season', [
            'competition_id' => $competition->id,
            'season_id' => $season->id,
            'groups_active' => true,
        ]);
        $this->assertDatabaseHas('tournament_knockout', [
            'competition_id' => $competition->id,
            'participant_count' => 4,
            'status' => 'in_progress',
        ]);
        $this->assertSame(4, DB::table('games')->whereNotNull('knockout_tie_id')->count());
        $this->assertSame(
            '2027-02-16',
            substr((string) Game::query()->whereNotNull('knockout_tie_id')->min('match_start'), 0, 10)
        );

        $firstRoundGames = Game::query()->whereNotNull('knockout_tie_id')->get();
        foreach ($firstRoundGames as $game) {
            $game->winner = $game->leg_number === 1 ? 1 : 2;
            $game->status = Game::STATUS_COMPLETED;
            $game->home_team_goals = $game->leg_number === 1 ? 2 : 0;
            $game->away_team_goals = $game->leg_number === 1 ? 0 : 1;
            $game->save();
        }

        $updater->setGamesByCompetition([
            $competition->id => $firstRoundGames->map(fn (Game $game) => $game->fresh()->toArray())->all(),
        ]);
        $updater->distributeGamesForCompetitionUpdates($season, $instance->id);

        $finalTieId = DB::table('tournament_knockout_ties AS ties')
            ->join('tournament_knockout_rounds AS rounds', 'rounds.id', '=', 'ties.round_id')
            ->where('rounds.bracket_side', 'final')
            ->value('ties.id');

        $this->assertNotNull($finalTieId);
        $this->assertSame(1, DB::table('games')->where('knockout_tie_id', $finalTieId)->count());
        $this->assertDatabaseHas('tournament_knockout_ties', [
            'id' => $finalTieId,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function league_competitions_use_a_null_groups_state(): void
    {
        $competition = Competition::factory()->create(['type' => 'league']);

        $this->assertNull($competition->groups);
    }

    private function competitionUpdater(): CompetitionUpdater
    {
        $repository = new CompetitionRepository(
            new CompetitionDataSource,
            app(GameContext::class),
            app(GameService::class),
        );

        return new CompetitionUpdater(
            new LeagueUpdater($repository),
            new TournamentUpdater($repository)
        );
    }

    private function groupRow(int $competitionId, int $groupId, int $clubId, int $points): array
    {
        return [
            'instance_id' => 1,
            'competition_id' => $competitionId,
            'season_id' => 1,
            'group_id' => $groupId,
            'groups_active' => true,
            'club_id' => $clubId,
            'points' => $points,
        ];
    }
}
