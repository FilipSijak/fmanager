<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function completed_appearances_are_aggregated_by_competition_in_the_current_season(): void
    {
        [$instance, $player] = $this->world();
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'League']);
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Cup']);
        $this->appearance($instance, $player, $league, ['goals' => 2, 'assists' => 1, 'rating' => 8, 'minutes_played' => 90]);
        $this->appearance($instance, $player, $league, ['goals' => 1, 'rating' => 6, 'minutes_played' => 30]);
        $this->appearance($instance, $player, $cup, ['rating' => 9]);
        $this->appearance($instance, $player, $league, ['goals' => 9], ['status' => Game::STATUS_IN_PROGRESS]);
        $this->appearance($instance, $player, $league, ['goals' => 9], ['match_start' => '2026-09-02 15:00:00']);
        $pastSeason = Season::factory()->create(['instance_id' => $instance->id]);
        $this->appearance($instance, $player, $league, ['goals' => 9], ['season_id' => $pastSeason->id]);
        $this->appearance($instance, $player, $league, ['goals' => 9, 'minutes_played' => 0]);
        $otherPlayer = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $player->club_id]);
        $this->appearance($instance, $otherPlayer, $league, ['goals' => 9]);

        $this->getJson('/api/player/'.$player->id)->assertOk()
            ->assertJsonPath('data.performance.season_id', $instance->season_id)
            ->assertJsonCount(2, 'data.performance.competitions')
            ->assertJsonPath('data.performance.competitions.0.name', 'Cup')
            ->assertJsonPath('data.performance.competitions.1', [
                'id' => $league->id, 'name' => 'League', 'appearances' => 2,
                'minutes' => 120, 'goals' => 3, 'assists' => 1, 'average_rating' => 7,
            ]);
    }

    #[Test]
    public function recent_form_contains_only_the_last_five_completed_appearances_in_date_order(): void
    {
        [$instance, $player] = $this->world();
        $opponent = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Opponent', 'stadium_id' => 200]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id]);
        for ($day = 1; $day <= 6; $day++) {
            $this->appearance($instance, $player, $competition, ['rating' => 6], [
                'awayteam_id' => $opponent->id, 'match_start' => "2026-08-0{$day} 15:00:00",
            ]);
        }
        $latest = $this->appearance($instance, $player, $competition, ['rating' => 8.5, 'minutes_played' => 25], [
            'hometeam_id' => $opponent->id, 'awayteam_id' => $player->club_id,
            'home_team_goals' => 1, 'away_team_goals' => 2,
        ]);
        $this->appearance($instance, $player, $competition, ['rating' => 10], ['status' => Game::STATUS_IN_PROGRESS]);
        $this->appearance($instance, $player, $competition, ['rating' => 10, 'minutes_played' => 0]);

        $this->getJson('/api/player/'.$player->id)->assertOk()
            ->assertJsonCount(5, 'data.performance.recent')
            ->assertJsonPath('data.performance.recent.0', [
                'game_id' => $latest->id, 'date' => '2026-09-01', 'opponent' => 'Opponent', 'venue' => 'Away',
                'home_goals' => 1, 'away_goals' => 2, 'minutes' => 25, 'goals' => 0, 'assists' => 0, 'rating' => 8.5,
            ])
            ->assertJsonPath('data.performance.recent.1.date', '2026-08-06')
            ->assertJsonPath('data.performance.recent.1.venue', 'Home')
            ->assertJsonPath('data.performance.recent.4.date', '2026-08-03');
    }

    #[Test]
    public function a_player_without_appearances_has_an_empty_summary(): void
    {
        [, $player] = $this->world();

        $this->getJson('/api/player/'.$player->id)->assertOk()->assertJsonPath('data.performance.competitions', [])->assertJsonPath('data.performance.recent', []);
    }

    #[Test]
    public function another_saves_player_returns_404(): void
    {
        [$instance] = $this->world();
        $other = Instance::factory()->create(['season_id' => $instance->season_id]);
        $player = Player::factory()->create(['instance_id' => $other->id]);

        $this->getJson('/api/player/'.$player->id)->assertNotFound();
    }

    #[Test]
    public function guests_cannot_read_player_performance(): void
    {
        $this->getJson('/api/player/1')->assertUnauthorized();
    }

    /** @return array{Instance, Player} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100]);
        $instance->forceFill(['season_id' => $season->id, 'club_id' => $club->id])->save();
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id]);
        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])
            ->withSession(['active_instance_hash' => $instance->instance_hash]);
        app(GameContext::class)->set($instance->id, $season->id, '2026-09-01');

        return [$instance, $player];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $gameAttributes
     */
    private function appearance(Instance $instance, Player $player, Competition $competition, array $stats = [], array $gameAttributes = []): Game
    {
        $game = Game::factory()->create($gameAttributes + [
            'instance_id' => $instance->id, 'season_id' => $instance->season_id,
            'competition_id' => $competition->id, 'stadium_id' => 100,
            'hometeam_id' => $player->club_id, 'awayteam_id' => $player->club_id,
            'match_start' => '2026-09-01 15:00:00', 'status' => Game::STATUS_COMPLETED,
        ]);
        GamePlayerStat::factory()->create($stats + [
            'instance_id' => $instance->id, 'game_id' => $game->id,
            'club_id' => $player->club_id, 'player_id' => $player->id,
            'goals' => 0, 'assists' => 0, 'rating' => 7, 'minutes_played' => 90,
        ]);

        return $game;
    }
}
