<?php

namespace Tests\Feature;

use App\Events\MatchDayCompleted;
use App\Events\NextDay;
use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveMatchApiTest extends TestCase
{
    use RefreshDatabase;

    private const MATCH_DATE = '2026-08-15';

    private const SQUAD_POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST', 'GK', 'CB', 'RB', 'CM', 'LM', 'ST', 'ST'];

    #[Test]
    public function next_day_kicks_off_the_users_match_instead_of_moving_the_day_on(): void
    {
        [$instance, $game] = $this->matchDay();

        $response = $this->apiRequest($instance)->postJson('/api/instance/next-day');

        $response->assertOk()->assertJsonPath('data.live_game_id', $game->id)->assertJsonPath('data.date', self::MATCH_DATE);
        $this->assertSame(self::MATCH_DATE, $instance->fresh()->instance_date);
        $this->assertSame(Game::STATUS_IN_PROGRESS, $game->fresh()->status);
        $this->assertDatabaseCount('live_matches', 1);
    }

    #[Test]
    public function next_day_resumes_a_match_already_being_played(): void
    {
        [$instance, $game] = $this->matchDay();
        $this->apiRequest($instance)->postJson('/api/instance/next-day');
        $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 20]);

        $response = $this->apiRequest($instance)->postJson('/api/instance/next-day');

        $response->assertOk()->assertJsonPath('data.live_game_id', $game->id);
        $this->assertDatabaseCount('live_matches', 1);
        $this->apiRequest($instance)->getJson("/api/live-match/{$game->id}")->assertJsonPath('data.minute', 20);
    }

    #[Test]
    public function advancing_plays_the_match_on_by_the_requested_minutes(): void
    {
        [$instance, $game] = $this->liveMatch();

        $response = $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 10]);

        $response->assertOk()
            ->assertJsonPath('data.minute', 10)
            ->assertJsonPath('data.finished', false)
            ->assertJsonPath('data.user_side', 'home')
            ->assertJsonCount(11, 'data.home.on_pitch')
            ->assertJsonCount(7, 'data.home.bench');
        $this->assertSame(Game::STATUS_IN_PROGRESS, $game->fresh()->status);
    }

    #[Test]
    public function full_time_completes_the_game_and_stores_every_players_stats(): void
    {
        [$instance, $game] = $this->liveMatch();

        $response = $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 90]);

        $response->assertOk()->assertJsonPath('data.minute', 90)->assertJsonPath('data.finished', true);
        $completed = $game->fresh();
        $this->assertSame(Game::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->processed_at);
        $this->assertSame($response->json('data.home.goals'), $completed->home_team_goals);
        $this->assertSame(11, $completed->playerStats()->where('club_id', $game->hometeam_id)->count());
        $this->assertSame(11, $completed->playerStats()->where('club_id', $game->awayteam_id)->where('started', true)->count());
    }

    #[Test]
    public function after_full_time_next_day_moves_on_and_reports_the_users_result(): void
    {
        [$instance, $game] = $this->liveMatch();
        $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 90]);
        Event::fake([NextDay::class, MatchDayCompleted::class]);

        $response = $this->apiRequest($instance)->postJson('/api/instance/next-day');

        $response->assertOk()->assertJsonPath('data.live_game_id', null)->assertJsonPath('data.date', '2026-08-16');
        Event::assertDispatched(MatchDayCompleted::class, fn (MatchDayCompleted $event): bool => $event->games->pluck('id')->all() === [$game->id]);
    }

    #[Test]
    public function the_user_brings_a_substitute_on_for_a_player_on_the_pitch(): void
    {
        [$instance, $game] = $this->liveMatch();
        $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 30]);
        $match = $this->apiRequest($instance)->getJson("/api/live-match/{$game->id}")->json('data');
        $playerOff = $match['home']['on_pitch'][9]['player_id'];
        $playerOn = $match['home']['bench'][0]['player_id'];

        $response = $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/substitutions", [
            'player_off_id' => $playerOff,
            'player_on_id' => $playerOn,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.home.substitutions_used', 1)
            ->assertJsonPath('data.home.on_pitch.9.player_id', $playerOn)
            ->assertJsonPath('data.home.finished.0.player_id', $playerOff)
            ->assertJsonPath('data.home.finished.0.status', 'substituted')
            ->assertJsonCount(6, 'data.home.bench');
        $this->assertContains(
            ['minute' => 30, 'type' => 'substitution', 'player_id' => $playerOn, 'replaced_player_id' => $playerOff],
            array_map(fn (array $event): array => array_intersect_key($event, array_flip(['minute', 'type', 'player_id', 'replaced_player_id'])), $response->json('data.events')),
        );
    }

    #[Test]
    public function an_invalid_substitution_is_rejected_with_its_reason(): void
    {
        [$instance, $game] = $this->liveMatch();
        $match = $this->apiRequest($instance)->getJson("/api/live-match/{$game->id}")->json('data');

        $response = $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/substitutions", [
            'player_off_id' => $match['home']['bench'][0]['player_id'],
            'player_on_id' => $match['home']['bench'][1]['player_id'],
        ]);

        $response->assertUnprocessable()->assertJsonPath('error', 'The player coming off is not on the pitch.');
        $this->apiRequest($instance)->getJson("/api/live-match/{$game->id}")->assertJsonPath('data.home.substitutions_used', 0);
    }

    #[Test]
    public function advancing_requires_between_1_and_90_minutes(): void
    {
        [$instance, $game] = $this->liveMatch();

        $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('minutes');
    }

    #[Test]
    public function a_game_that_is_not_being_played_live_cannot_be_played(): void
    {
        [$instance, $game] = $this->matchDay();

        $this->apiRequest($instance)->postJson("/api/live-match/{$game->id}/advance", ['minutes' => 10])
            ->assertUnprocessable()->assertJsonPath('error', 'This match is not being played live.');
    }

    #[Test]
    public function the_live_match_page_shows_the_match_and_other_games_redirect_to_their_match_page(): void
    {
        $this->withoutVite();
        [$instance, $game] = $this->liveMatch();
        $session = ['active_instance_hash' => $instance->instance_hash];

        $this->actingAs($instance->user)->withSession($session)->get("/matches/{$game->id}/live")
            ->assertInertia(fn (Assert $page) => $page->component('matches/LiveMatch')->where('match.game_id', $game->id)->where('match.minute', 0));

        $otherGame = $this->game($instance, Club::query()->findOrFail($game->awayteam_id), Club::query()->findOrFail($game->hometeam_id), '2026-08-22');
        $this->actingAs($instance->user)->withSession($session)->get("/matches/{$otherGame->id}/live")
            ->assertRedirect("/matches/{$otherGame->id}");
    }

    /**
     * An instance on match day: the user's club at home to an AI club, both with managers and 18 players.
     *
     * @return array{0: Instance, 1: Game}
     */
    private function matchDay(): array
    {
        $instance = Instance::factory()->create(['instance_hash' => 'live-match-'.uniqid(), 'season_id' => 1, 'instance_date' => self::MATCH_DATE]);
        Season::factory()->create(['id' => 1, 'instance_id' => $instance->id, 'start_date' => '2026-08-01', 'end_date' => '2027-06-01']);
        $formation = BaseFormation::query()->create(['code' => '4-4-2', 'name' => '4-4-2', 'is_active' => true, 'tactical_tendency' => 'balanced']);

        foreach (array_slice(self::SQUAD_POSITIONS, 0, 11) as $index => $position) {
            $formation->slots()->create(['slot' => (string) ($index + 1), 'position' => $position, 'x' => 50, 'y' => 50]);
        }

        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league', 'groups' => null, 'clubs_number' => 2]);
        [$userClub, $aiClub] = Club::factory()->count(2)->create(['instance_id' => $instance->id])->all();
        $instance->forceFill(['club_id' => $userClub->id])->saveQuietly();

        foreach ([$userClub, $aiClub] as $club) {
            DB::table('competition_season')->insert(['instance_id' => $instance->id, 'competition_id' => $competition->id, 'season_id' => 1, 'club_id' => $club->id]);
            StaffCoaching::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'type' => PersonTypes::MANAGER]);

            foreach (self::SQUAD_POSITIONS as $position) {
                Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'position' => $position]);
            }
        }

        return [$instance->fresh(), $this->game($instance, $userClub, $aiClub, self::MATCH_DATE, $competition)];
    }

    /** @return array{0: Instance, 1: Game} */
    private function liveMatch(): array
    {
        [$instance, $game] = $this->matchDay();
        $this->apiRequest($instance)->postJson('/api/instance/next-day')->assertOk();

        return [$instance, $game->fresh()];
    }

    private function game(Instance $instance, Club $home, Club $away, string $date, ?Competition $competition = null): Game
    {
        return Game::factory()->create([
            'instance_id' => $instance->id,
            'season_id' => 1,
            'competition_id' => ($competition ?? Competition::query()->firstOrFail())->id,
            'hometeam_id' => $home->id,
            'awayteam_id' => $away->id,
            'stadium_id' => 1,
            'match_start' => $date.' 15:00:00',
        ]);
    }

    private function apiRequest(Instance $instance): self
    {
        return $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash]);
    }
}
