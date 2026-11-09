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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
    public function fitness_uses_stored_progress_and_only_injuries_active_on_the_game_date(): void
    {
        [$instance, $player] = $this->world();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 72, 'morale' => 65]);
        $injuryId = DB::table('injuries')->insertGetId(['type' => 'Knee injury', 'severity' => 4, 'duration_from' => 1, 'duration_to' => 20]);
        $foreignInstance = Instance::factory()->create(['season_id' => $instance->season_id]);
        foreach ([
            [$instance->id, '2026-08-30', '2026-09-01'],
            [$instance->id, '2026-08-10', '2026-08-31'],
            [$instance->id, '2026-09-02', '2026-09-10'],
            [$foreignInstance->id, '2026-08-30', '2026-09-10'],
        ] as [$instanceId, $start, $end]) {
            DB::table('player_injuries')->insert([
                'instance_id' => $instanceId, 'season_id' => $instance->season_id, 'player_id' => $player->id,
                'injury_id' => $injuryId, 'injury_start_date' => $start, 'injury_end_date' => $end,
            ]);
        }

        $this->getJson('/api/player/'.$player->id)->assertOk()->assertJsonPath('data.fitness', [
            'condition' => 72, 'morale' => 65, 'is_injured' => true,
            'injuries' => [['name' => 'Knee injury', 'starts_at' => '2026-08-30', 'ends_at' => '2026-09-01']],
        ]);
    }

    #[Test]
    public function missing_fitness_data_is_unknown_and_does_not_create_progress_records(): void
    {
        [, $player] = $this->world();
        DB::table('players_progress')->where('player_id', $player->id)->delete();

        $this->getJson('/api/player/'.$player->id)->assertOk()->assertJsonPath('data.fitness', [
            'condition' => null, 'morale' => null, 'is_injured' => false, 'injuries' => [],
        ]);
        $this->assertDatabaseMissing('players_progress', ['player_id' => $player->id]);
    }

    #[Test]
    public function selecting_a_past_season_shows_its_performance_after_a_player_changes_club(): void
    {
        [$instance, $player] = $this->world();
        $past = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']);
        $competition = Competition::factory()->create(['instance_id' => $instance->id]);
        $opponent = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Past opponent', 'stadium_id' => 200]);
        $pastGame = $this->appearance($instance, $player, $competition, ['goals' => 2, 'rating' => 8], [
            'season_id' => $past->id, 'match_start' => '2026-05-01 15:00:00', 'awayteam_id' => $opponent->id,
        ]);
        $this->appearance($instance, $player, $competition, ['goals' => 5]);
        $player->forceFill(['club_id' => $opponent->id])->save();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 73]);

        $this->getJson('/api/player/'.$player->id.'?season_id='.$past->id)->assertOk()
            ->assertJsonPath('data.performance.season_id', $past->id)
            ->assertJsonPath('data.performance.current_season_id', $instance->season_id)
            ->assertJsonPath('data.performance.competitions.0.appearances', 1)
            ->assertJsonPath('data.performance.competitions.0.goals', 2)
            ->assertJsonPath('data.performance.recent.0.game_id', $pastGame->id)
            ->assertJsonPath('data.performance.recent.0.opponent', 'Past opponent')
            ->assertJsonPath('data.performance.recent.0.venue', 'Home')
            ->assertJsonPath('data.fitness.condition', 73);
    }

    #[Test]
    public function available_seasons_are_ordered_and_exclude_future_seasons_and_other_saves(): void
    {
        [$instance, $player] = $this->world();
        $past = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']);
        Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
        $foreignInstance = Instance::factory()->create();
        Season::factory()->create(['instance_id' => $foreignInstance->id, 'start_date' => '2025-07-01']);

        $this->getJson('/api/player/'.$player->id)->assertOk()
            ->assertJsonCount(2, 'data.performance.seasons')
            ->assertJsonPath('data.performance.seasons.0.id', $instance->season_id)
            ->assertJsonPath('data.performance.seasons.1', ['id' => $past->id, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']);
    }

    #[Test]
    public function another_saves_season_and_a_future_season_return_404(): void
    {
        [$instance, $player] = $this->world();
        $other = Instance::factory()->create();
        $foreignSeason = Season::factory()->create(['instance_id' => $other->id, 'start_date' => '2025-07-01']);
        $futureSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2027-07-01']);

        $this->getJson('/api/player/'.$player->id.'?season_id='.$foreignSeason->id)->assertNotFound();
        $this->getJson('/api/player/'.$player->id.'?season_id='.$futureSeason->id)->assertNotFound();
        $this->getJson('/api/player/'.$player->id.'?season_id=999999')->assertNotFound();
    }

    #[Test]
    #[TestWith(['zero'])]
    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith([1.5])]
    public function invalid_season_selection_returns_422(mixed $seasonId): void
    {
        [, $player] = $this->world();

        $this->getJson('/api/player/'.$player->id.'?season_id='.$seasonId)
            ->assertUnprocessable()->assertJsonValidationErrors('season_id');
    }

    #[Test]
    public function a_profile_without_a_current_season_still_returns_empty_performance(): void
    {
        [$instance, $player] = $this->world();
        $instance->forceFill(['season_id' => null])->save();

        $this->getJson('/api/player/'.$player->id)->assertOk()
            ->assertJsonPath('data.performance.season_id', null)
            ->assertJsonPath('data.performance.competitions', [])
            ->assertJsonPath('data.performance.recent', []);
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
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
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
