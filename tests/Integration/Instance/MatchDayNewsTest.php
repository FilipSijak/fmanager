<?php

namespace Tests\Integration\Instance;

use App\Events\MatchDayCompleted;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Services\GameService\CompleteGameService;
use App\Services\InstanceService\InstanceService;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\MatchEngineService\MatchSimulationResult;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchDayNewsTest extends TestCase
{
    use RefreshDatabase;

    private Instance $instance;

    private Competition $league;

    private Club $userClub;

    protected function setUp(): void
    {
        parent::setUp();

        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        $this->instance = Instance::factory()->create(['id' => 1, 'season_id' => 1, 'instance_date' => '2026-08-15']);
        app(GameContext::class)->set(1, 1, '2026-08-15');
        $this->league = Competition::factory()->create(['instance_id' => 1, 'name' => 'Premier League']);
        $this->userClub = $this->clubIn($this->league, 'Arsenal');
        $this->instance->club_id = $this->userClub->id;
        $this->instance->save();
    }

    #[Test]
    public function next_day_announces_the_games_it_completed(): void
    {
        Event::fake([MatchDayCompleted::class]);
        $game = $this->game($this->userClub, $this->clubIn($this->league, 'Chelsea'), null, null);
        $completedGame = $game->replicate()->forceFill(['id' => $game->id, 'home_team_goals' => 2, 'away_team_goals' => 1]);
        $this->mock(MatchSimulationEngine::class)->shouldReceive('simulate')->once()
            ->andReturn(new MatchSimulationResult(2, 1, []));
        $this->mock(CompleteGameService::class)->shouldReceive('complete')->once()
            ->with($game->id, 2, 1, [])->andReturn($completedGame);

        app(InstanceService::class)->nextDay();

        Event::assertDispatched(MatchDayCompleted::class, fn (MatchDayCompleted $event): bool => $event->instance->is($this->instance)
            && $event->games->pluck('id')->all() === [$game->id]);
    }

    #[Test]
    public function match_day_news_reports_the_users_result_and_their_league_round_up(): void
    {
        $chelsea = $this->clubIn($this->league, 'Chelsea');
        $leeds = $this->clubIn($this->league, 'Leeds');
        $everton = $this->clubIn($this->league, 'Everton');
        $otherLeague = Competition::factory()->create(['instance_id' => 1, 'name' => 'La Liga']);
        $games = collect([
            $this->game($chelsea, $this->userClub, 3, 1),
            $this->game($leeds, $everton, 0, 0),
            $this->game($this->clubIn($otherLeague, 'Betis'), $this->clubIn($otherLeague, 'Getafe'), 1, 0, $otherLeague),
        ]);

        event(new MatchDayCompleted($this->instance, $games));

        $this->assertSame([
            [
                'club_id' => $this->userClub->id, 'competition_id' => $this->league->id, 'type' => 'match',
                'title' => 'Chelsea 3-1 Arsenal', 'content' => 'Arsenal lost 1-3 to Chelsea in the Premier League.',
            ],
            [
                'club_id' => null, 'competition_id' => $this->league->id, 'type' => 'match',
                'title' => 'Premier League round-up', 'content' => "Chelsea 3-1 Arsenal\nLeeds 0-0 Everton",
            ],
        ], DB::table('news')->orderBy('id')->get(['club_id', 'competition_id', 'type', 'title', 'content'])
            ->map(fn ($news): array => (array) $news)->all());
    }

    #[Test]
    public function match_days_without_the_users_league_publish_no_news(): void
    {
        $otherLeague = Competition::factory()->create(['instance_id' => 1]);

        event(new MatchDayCompleted($this->instance, collect([
            $this->game($this->clubIn($otherLeague, 'Betis'), $this->clubIn($otherLeague, 'Getafe'), 1, 0, $otherLeague),
        ])));

        $this->assertSame(0, DB::table('news')->count());
    }

    #[Test]
    public function unemployed_managers_get_the_round_up_of_the_league_they_follow(): void
    {
        $this->instance->club_id = null;
        $this->instance->followed_competition_id = $this->league->id;
        $this->instance->save();

        event(new MatchDayCompleted($this->instance, collect([$this->game($this->userClub, $this->clubIn($this->league, 'Chelsea'), 1, 1)])));

        $this->assertSame([['club_id' => null, 'title' => 'Premier League round-up', 'content' => 'Arsenal 1-1 Chelsea']],
            DB::table('news')->get(['club_id', 'title', 'content'])->map(fn ($news): array => (array) $news)->all());
    }

    private function clubIn(Competition $competition, string $name): Club
    {
        $club = Club::factory()->create(['instance_id' => 1, 'name' => $name]);
        DB::table('competition_season')->insert([
            'instance_id' => 1, 'season_id' => 1, 'competition_id' => $competition->id, 'club_id' => $club->id,
        ]);

        return $club;
    }

    private function game(Club $home, Club $away, ?int $homeGoals, ?int $awayGoals, ?Competition $competition = null): Game
    {
        return Game::factory()->create([
            'instance_id' => 1,
            'season_id' => 1,
            'competition_id' => ($competition ?? $this->league)->id,
            'hometeam_id' => $home->id,
            'awayteam_id' => $away->id,
            'stadium_id' => 1,
            'match_start' => '2026-08-15 15:00:00',
            'home_team_goals' => $homeGoals,
            'away_team_goals' => $awayGoals,
        ]);
    }
}
