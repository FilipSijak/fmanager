<?php

namespace Tests\Integration\Services\GameService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Services\GameService\CompleteGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function completing_a_league_game_updates_the_table_exactly_once(): void
    {
        [$instance, $season, $competition, $clubs] = $this->competition('league', null);
        $game = $this->game($instance, $season, $competition, $clubs[0], $clubs[1]);
        $service = app(CompleteGameService::class);
        $completed = $service->complete($game->id, 3, 1);
        $service->complete($game->id, 9, 0);

        $this->assertSame(Game::STATUS_COMPLETED, $completed->status);
        $this->assertSame(1, $completed->winner);
        $this->assertNotNull($completed->processed_at);
        $this->assertDatabaseHas('competition_season', ['club_id' => 1, 'played' => 1, 'wins' => 1, 'points' => 3, 'goals_for' => 3, 'goals_against' => 1]);
        $this->assertDatabaseHas('competition_season', ['club_id' => 2, 'played' => 1, 'losses' => 1, 'points' => 0, 'goals_for' => 1, 'goals_against' => 3]);
        $this->assertDatabaseHas('games', ['id' => $game->id, 'home_team_goals' => 3, 'away_team_goals' => 1]);
    }

    #[Test]
    public function a_postponed_game_remains_pending_on_its_new_date(): void
    {
        [$instance, $season, $competition, $clubs] = $this->competition('league', null);
        $game = $this->game($instance, $season, $competition, $clubs[0], $clubs[1]);
        $postponed = app(CompleteGameService::class)->postpone($game->id, '2026-09-20 15:00:00');

        $this->assertSame(Game::STATUS_POSTPONED, $postponed->status);
        $this->assertNull($postponed->processed_at);
        $this->assertSame('2026-09-20 15:00:00', $postponed->match_start);
    }

    #[Test]
    public function cancelling_the_last_group_game_creates_the_knockout_without_awarding_points(): void
    {
        [$instance, $season, $competition, $clubs] = $this->competition('tournament', 1, 4);
        $game = $this->game($instance, $season, $competition, $clubs[0], $clubs[1]);
        $cancelled = app(CompleteGameService::class)->cancel($game->id);

        $this->assertSame(Game::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->processed_at);
        $this->assertDatabaseHas('competition_season', ['club_id' => 1, 'played' => 0]);
        $this->assertDatabaseHas('competitions', ['id' => 1, 'groups' => 1]);
        $this->assertDatabaseMissing('competition_season', [
            'competition_id' => 1,
            'season_id' => 1,
            'groups_active' => true,
        ]);
        $this->assertDatabaseHas('tournament_knockout', ['competition_id' => 1, 'participant_count' => 4, 'status' => 'in_progress']);
        $this->assertSame(4, DB::table('games')->whereNotNull('knockout_tie_id')->count());
    }

    private function competition(string $type, ?int $groups, int $clubCount = 2): array
    {
        $instance = Instance::factory()->create(['id' => 1, 'season_id' => 1]);
        $season = Season::factory()->create(['id' => 1, 'instance_id' => 1, 'start_date' => '2026-08-15', 'end_date' => '2027-08-15']);
        $competition = Competition::factory()->create(['id' => 1, 'instance_id' => 1, 'type' => $type, 'groups' => $groups, 'clubs_number' => $clubCount]);
        $clubs = collect();
        for ($id = 1; $id <= $clubCount; $id++) {
            $clubs->push(Club::factory()->create(['id' => $id, 'instance_id' => 1, 'stadium_id' => 1000 + $id]));
            DB::table('competition_season')->insert(['instance_id' => 1, 'competition_id' => 1, 'season_id' => 1, 'club_id' => $id, 'group_id' => $groups === 1 ? ($id <= 2 ? 1 : 2) : null, 'groups_active' => $groups === 1, 'points' => $groups === 1 ? $clubCount - $id : 0]);
        }

        return [$instance, $season, $competition, $clubs];
    }

    private function game(Instance $instance, Season $season, Competition $competition, Club $home, Club $away): Game
    {
        return Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id, 'hometeam_id' => $home->id, 'awayteam_id' => $away->id, 'stadium_id' => $home->stadium_id, 'match_start' => '2026-08-15 15:00:00']);
    }
}
