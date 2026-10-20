<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\Stadium;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MatchPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function guests_must_sign_in_to_view_matches(): void
    {
        $this->get('/matches/1')->assertRedirect('/login');
    }

    #[Test]
    public function completed_matches_show_the_score_venue_and_chronological_saved_events(): void
    {
        [$instance, $game] = $this->fixture();
        $game->status = 'completed';
        $game->home_team_goals = 2;
        $game->away_team_goals = 0;
        $game->attendance = 12345;
        $game->match_summary = json_encode(['events' => [
            ['minute' => 70, 'type' => 'goal', 'club_id' => $game->hometeam_id],
            ['minute' => 15, 'type' => 'goal', 'club_id' => $game->hometeam_id],
        ]], JSON_THROW_ON_ERROR);
        $game->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->component('matches/Match')->where('match.home_team', 'Home FC')->where('match.away_team', 'Away FC')
            ->where('match.home_team_goals', 2)->where('match.away_team_goals', 0)
            ->where('match.stadium_name', 'Main Ground')->where('match.attendance', 12345)
            ->where('match.competition_name', 'Our League')->missing('match.match_summary')
            ->has('events', 2)->where('events.0.minute', 15)->where('events.1.minute', 70)
            ->where('events.0.id', $game->id.':1')
            ->where('events.0.club_id', $game->hometeam_id)->where('events.0.type', 'goal'));
    }

    #[Test]
    #[TestWith(['scheduled'])]
    #[TestWith(['postponed'])]
    #[TestWith(['cancelled'])]
    #[TestWith(['abandoned'])]
    public function fixtures_without_a_summary_preserve_their_status_and_have_no_invented_events(string $status): void
    {
        [$instance, $game] = $this->fixture();
        $game->status = $status;
        $game->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('match.status', $status)->where('match.home_team_goals', null)
            ->where('match.match_start', '2026-09-01 15:00:00')->has('events', 0));
    }

    #[Test]
    public function historical_matches_link_to_their_own_season_without_changing_the_active_game(): void
    {
        [$instance, $game] = $this->fixture();
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2025-08-01', 'end_date' => '2026-06-15']);
        $game->season_id = $oldSeason->id;
        $game->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('match.season_id', $oldSeason->id)->where('match.season_start', '2025-08-01'));

        $this->assertSame($instance->season_id, app(GameContext::class)->seasonId());
    }

    #[Test]
    public function unknown_venues_and_incomplete_saved_events_do_not_break_the_match_page(): void
    {
        [$instance, $game] = $this->fixture();
        $game->stadium_id = 999999;
        $game->match_summary = json_encode(['events' => [null, ['type' => 'goal'], ['minute' => 10, 'type' => 'goal']]], JSON_THROW_ON_ERROR);
        $game->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/'.$game->id)->assertInertia(fn (Assert $page) => $page
            ->where('match.stadium_name', null)->has('events', 1)
            ->where('events.0.minute', 10)->where('events.0.club_id', null));
    }

    #[Test]
    public function foreign_matches_are_not_accessible(): void
    {
        [$instance, $game] = $this->fixture();
        $game->instance_id = $instance->id + 1;
        $game->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/'.$game->id)->assertNotFound();
    }

    #[Test]
    public function matches_with_foreign_clubs_do_not_expose_their_data(): void
    {
        [$instance, $game] = $this->fixture();
        $foreignClub = Club::factory()->create(['instance_id' => $instance->id + 1]);
        $game->awayteam_id = $foreignClub->id;
        $game->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/'.$game->id)->assertNotFound();
    }

    #[Test]
    public function missing_matches_return_not_found(): void
    {
        [$instance] = $this->fixture();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/matches/999999')->assertNotFound();
    }

    /** @return array{Instance, Game} */
    private function fixture(): array
    {
        $instance = Instance::factory()->create();
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->season_id = $season->id;
        $instance->save();
        $home = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Home FC']);
        $away = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Away FC']);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'name' => 'Main Ground']);
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Our League']);
        $game = Game::factory()->create([
            'instance_id' => $instance->id, 'season_id' => $season->id,
            'competition_id' => $competition->id, 'hometeam_id' => $home->id,
            'awayteam_id' => $away->id, 'stadium_id' => $stadium->id,
            'match_start' => '2026-09-01 15:00:00',
        ]);

        return [$instance, $game];
    }
}
