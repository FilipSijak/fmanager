<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Models\Season;
use App\Models\Stadium;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function guests_must_sign_in_to_view_clubs(): void
    {
        $this->get('/clubs/1')->assertRedirect('/login');
    }

    #[Test]
    public function club_overviews_include_public_details_and_the_active_squad(): void
    {
        [$instance, $club] = $this->world();
        $player = $this->player($instance, $club, 'Active Player');
        $this->player($instance, $club, 'Retired Player', ['is_retired' => true]);
        $this->player($instance, $club, 'Foreign Player', ['instance_id' => $instance->id + 1]);
        $otherClub = Club::factory()->create(['instance_id' => $instance->id]);
        $this->player($instance, $otherClub, 'Other Club Player');

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/'.$club->id)->assertInertia(fn (Assert $page) => $page
            ->component('clubs/Club')->where('club.name', 'Our Club')
            ->where('club.stadium.name', 'Our Ground')->where('club.stadium.capacity', 50000)
            ->missing('club.account')->missing('club.financial_rank')
            ->has('squad', 1)->where('squad.0.id', $player->id)->where('squad.0.name', 'Active Player')
            ->missing('squad.0.salary')->where('season.id', $instance->season_id));
    }

    #[Test]
    public function club_competitions_and_matches_are_limited_to_the_selected_club_and_season(): void
    {
        [$instance, $club] = $this->world();
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Our League']);
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2025-08-01', 'end_date' => '2026-06-15']);
        $rivals = Club::factory()->count(2)->create(['instance_id' => $instance->id]);
        DB::table('competition_season')->insert([
            ['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $league->id, 'club_id' => $club->id, 'points' => 10],
            ['instance_id' => $instance->id, 'season_id' => $oldSeason->id, 'competition_id' => $league->id, 'club_id' => $club->id, 'points' => 4],
        ]);
        $attributes = ['instance_id' => $instance->id, 'competition_id' => $league->id, 'hometeam_id' => $club->id, 'awayteam_id' => $rivals[0]->id, 'stadium_id' => $club->stadium_id];
        Game::factory()->create([...$attributes, 'season_id' => $instance->season_id]);
        $oldMatch = Game::factory()->create([...$attributes, 'season_id' => $oldSeason->id, 'status' => 'completed', 'home_team_goals' => 1, 'away_team_goals' => 0]);
        Game::factory()->create([...$attributes, 'season_id' => $oldSeason->id, 'hometeam_id' => $rivals[1]->id]);
        Game::factory()->create([...$attributes, 'season_id' => $oldSeason->id, 'instance_id' => $instance->id + 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/'.$club->id.'?season_id='.$oldSeason->id)->assertInertia(fn (Assert $page) => $page
            ->has('competitions', 1)->where('competitions.0.name', 'Our League')
            ->where('competitions.0.points', 4)->has('matches', 1)
            ->where('matches.0.id', $oldMatch->id)->where('matches.0.season_id', $oldSeason->id)
            ->where('matches.0.competition_name', 'Our League')->has('seasons', 2));

        $this->assertSame($instance->season_id, app(GameContext::class)->seasonId());
    }

    #[Test]
    public function unemployed_managers_can_view_clubs_with_empty_competitions_and_squads(): void
    {
        [$instance, $club] = $this->world();
        $instance->club_id = null;
        $instance->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/'.$club->id)->assertInertia(fn (Assert $page) => $page
            ->where('club.id', $club->id)->has('competitions', 0)->has('matches', 0)->has('squad', 0));
    }

    #[Test]
    public function clubs_from_other_game_worlds_are_not_accessible(): void
    {
        [$instance] = $this->world();
        $otherClub = Club::factory()->create(['instance_id' => $instance->id + 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/'.$otherClub->id)->assertNotFound();
    }

    #[Test]
    public function foreign_seasons_are_not_accessible_from_club_pages(): void
    {
        [$instance, $club] = $this->world();
        $otherSeason = Season::factory()->create(['instance_id' => $instance->id + 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/'.$club->id.'?season_id='.$otherSeason->id)->assertNotFound();
    }

    #[Test]
    public function club_pages_validate_the_season_filter(): void
    {
        [$instance, $club] = $this->world();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->getJson('/clubs/'.$club->id.'?season_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('season_id');
    }

    #[Test]
    public function a_club_does_not_expose_a_stadium_from_another_game(): void
    {
        [$instance, $club] = $this->world();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id + 1]);
        $club->stadium_id = $stadium->id;
        $club->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/'.$club->id)->assertInertia(fn (Assert $page) => $page->where('club.stadium', null));
    }

    #[Test]
    public function missing_clubs_return_not_found(): void
    {
        [$instance] = $this->world();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/clubs/999999')->assertNotFound();
    }

    /** @return array{Instance, Club} */
    private function world(): array
    {
        $instance = Instance::factory()->create();
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-01', 'end_date' => '2027-06-15']);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'name' => 'Our Ground', 'capacity' => 50000]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Our Club', 'stadium_id' => $stadium->id]);
        $instance->season_id = $season->id;
        $instance->club_id = $club->id;
        $instance->save();

        return [$instance, $club];
    }

    /** @param array<string, int|bool> $attributes */
    private function player(Instance $instance, Club $club, string $name, array $attributes = []): Player
    {
        [$first, $last] = explode(' ', $name, 2);
        $person = Person::factory()->create(['instance_id' => $instance->id, 'first_name' => $first, 'last_name' => $last]);

        return Player::factory()->create([
            'instance_id' => $instance->id, 'club_id' => $club->id, 'person_id' => $person->id, ...$attributes,
        ]);
    }
}
