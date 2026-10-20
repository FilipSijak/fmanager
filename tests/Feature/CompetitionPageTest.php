<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionProgressionRule;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CompetitionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    #[TestWith(['/competitions'])]
    #[TestWith(['/competitions/1'])]
    #[TestWith(['/league-table'])]
    public function guests_must_sign_in(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
    }

    #[Test]
    #[TestWith(['/competitions'])]
    #[TestWith(['/league-table'])]
    public function it_opens_the_followed_competition_including_through_the_legacy_link(string $path): void
    {
        $instance = $this->gameWorld();
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Followed League']);
        $instance->followed_competition_id = $league->id;
        $instance->save();
        Competition::factory()->create(['instance_id' => $instance->id + 1, 'name' => 'Private League']);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get($path)->assertInertia(fn (Assert $page) => $page
            ->component('competitions/Competition')
            ->has('competitions', 1)
            ->where('competition.name', 'Followed League')
            ->where('season.start_date', '2026-08-01')
            ->where('clubId', $instance->club_id));
    }

    #[Test]
    public function it_defaults_to_the_managed_clubs_league(): void
    {
        $instance = $this->gameWorld();
        Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Other League', 'rank' => 1]);
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'name' => 'Our League', 'rank' => 2]);
        $this->membership($instance, $league, $instance->club_id);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions')->assertInertia(fn (Assert $page) => $page
            ->where('competition.name', 'Our League'));
    }

    #[Test]
    public function league_standings_and_matches_are_ordered_and_scoped_to_the_current_season(): void
    {
        $instance = $this->gameWorld();
        $league = Competition::factory()->create(['instance_id' => $instance->id]);
        $rival = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Rivals']);
        $this->membership($instance, $league, $instance->club_id, ['points' => 3, 'goals_for' => 2, 'goals_against' => 1, 'played' => 1, 'wins' => 1]);
        $this->membership($instance, $league, $rival->id, ['points' => 3, 'goals_for' => 3, 'played' => 1, 'wins' => 1]);
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2025-08-01']);
        $this->membership($instance, $league, $instance->club_id, ['season_id' => $oldSeason->id, 'points' => 100]);
        $foreignClub = Club::factory()->create(['instance_id' => $instance->id + 1]);
        $this->membership($instance, $league, $foreignClub->id, ['instance_id' => $instance->id + 1, 'points' => 200]);
        $later = $this->match($instance, $league, $instance->club_id, $rival->id, ['match_start' => '2026-08-20 15:00:00', 'status' => Game::STATUS_POSTPONED]);
        $earlier = $this->match($instance, $league, $rival->id, $instance->club_id, ['match_start' => '2026-08-10 15:00:00', 'status' => Game::STATUS_COMPLETED, 'home_team_goals' => 0, 'away_team_goals' => 2]);
        $this->match($instance, $league, $rival->id, $instance->club_id, ['season_id' => $oldSeason->id]);
        $this->match($instance, $league, $rival->id, $instance->club_id, ['instance_id' => $instance->id + 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$league->id)->assertInertia(fn (Assert $page) => $page
            ->has('table', 2)->where('table.0.club_name', 'Rivals')
            ->where('table.0.position', 1)->where('table.1.position', 2)
            ->where('table.1.goal_difference', 1)->where('table.1.points', 3)
            ->has('table.0.outcomes', 0)
            ->has('matches', 2)->where('matches.0.id', $earlier->id)
            ->where('matches.0.home_team', 'Rivals')->where('matches.0.away_team_goals', 2)
            ->where('matches.1.id', $later->id)->where('matches.1.status', 'postponed')
            ->where('matches.1.home_team_goals', null)
            ->has('groups', 0)->has('rounds', 0));
    }

    #[Test]
    public function cups_show_both_legs_future_opponents_and_the_single_match_final(): void
    {
        $instance = $this->gameWorld();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament', 'groups' => 0]);
        $rival = Club::factory()->create(['instance_id' => $instance->id]);
        $knockoutId = $this->knockout($instance, $cup);
        $roundId = $this->round($knockoutId, 1, 'first', 'semi_finals', 2);
        $tieId = $this->tie($roundId, $instance->club_id, $rival->id, $instance->club_id);
        $otherRoundId = $this->round($knockoutId, 1, 'second', 'semi_finals', 2);
        $this->tie($otherRoundId);
        $finalRoundId = $this->round($knockoutId, 2, 'final', 'final', 1);
        $this->tie($finalRoundId);
        $this->match($instance, $cup, $instance->club_id, $rival->id, ['knockout_tie_id' => $tieId, 'leg_number' => 1, 'status' => 'completed', 'home_team_goals' => 2, 'away_team_goals' => 0]);
        $this->match($instance, $cup, $rival->id, $instance->club_id, ['knockout_tie_id' => $tieId, 'leg_number' => 2, 'status' => 'completed', 'home_team_goals' => 1, 'away_team_goals' => 0]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$cup->id)->assertInertia(fn (Assert $page) => $page
            ->has('table', 0)->has('groups', 0)->has('rounds', 3)->has('matches', 2)
            ->where('rounds.0.number_of_legs', 2)
            ->where('rounds.0.ties.0.winner_club_id', $instance->club_id)
            ->where('rounds.0.ties.0.home_team', 'Managed FC')
            ->where('rounds.1.ties.0.home_team', null)
            ->where('rounds.2.bracket_side', 'final')->where('rounds.2.number_of_legs', 1)
            ->where('matches.1.leg_number', 2));
    }

    #[Test]
    public function standings_mark_promotion_relegation_and_qualification_using_the_progression_rules(): void
    {
        $instance = $this->gameWorld();
        $league = $this->progressionCompetition($instance, 'Our League');
        $upper = $this->progressionCompetition($instance, 'Upper League');
        $lower = $this->progressionCompetition($instance, 'Lower League');
        $continental = $this->progressionCompetition($instance, 'Champions Cup', 'tournament');
        $middle = Club::factory()->create(['instance_id' => $instance->id]);
        $bottom = Club::factory()->create(['instance_id' => $instance->id]);
        $this->membership($instance, $league, $instance->club_id, ['points' => 10]);
        $this->membership($instance, $league, $middle->id, ['points' => 5]);
        $this->membership($instance, $league, $bottom->id, ['points' => 1]);
        CompetitionProgressionRule::create([
            'source_base_competition_id' => $league->base_competition_id,
            'target_base_competition_id' => $upper->base_competition_id,
            'progression_type' => 'promotion', 'selector_type' => 'position_range',
            'position_from' => 1, 'position_to' => 1,
        ]);
        CompetitionProgressionRule::create([
            'source_base_competition_id' => $league->base_competition_id,
            'target_base_competition_id' => $lower->base_competition_id,
            'progression_type' => 'relegation', 'selector_type' => 'bottom_positions', 'places' => 1,
        ]);
        CompetitionProgressionRule::create([
            'source_base_competition_id' => $league->base_competition_id,
            'target_base_competition_id' => $continental->base_competition_id,
            'progression_type' => 'continental', 'selector_type' => 'position_range',
            'position_from' => 1, 'position_to' => 1, 'entry_stage' => 'group_stage',
        ]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$league->id)->assertInertia(fn (Assert $page) => $page
            ->has('table.0.outcomes', 2)->where('table.0.outcomes.0.type', 'promotion')
            ->where('table.0.outcomes.0.target_name', 'Upper League')
            ->where('table.0.outcomes.1.target_name', 'Champions Cup')
            ->where('table.0.outcomes.1.entry_stage', 'group_stage')
            ->has('table.1.outcomes', 0)->where('table.2.outcomes.0.type', 'relegation')
            ->where('table.2.outcomes.0.target_name', 'Lower League'));
    }

    #[Test]
    public function tied_standings_use_the_same_club_order_as_progression_calculations(): void
    {
        $instance = $this->gameWorld();
        $instance->club->name = 'Zebra FC';
        $instance->club->save();
        $league = Competition::factory()->create(['instance_id' => $instance->id]);
        $rival = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Alpha FC']);
        $this->membership($instance, $league, $instance->club_id);
        $this->membership($instance, $league, $rival->id);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$league->id)->assertInertia(fn (Assert $page) => $page
            ->where('table.0.club_name', 'Zebra FC')->where('table.1.club_name', 'Alpha FC'));
    }

    #[Test]
    public function group_tables_remain_available_after_the_knockout_phase_begins(): void
    {
        $instance = $this->gameWorld();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament', 'groups' => 0]);
        $rival = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Group Leader']);
        $other = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Other Group']);
        $this->membership($instance, $cup, $instance->club_id, ['group_id' => 1, 'groups_active' => false, 'points' => 4]);
        $this->membership($instance, $cup, $rival->id, ['group_id' => 1, 'groups_active' => false, 'points' => 6]);
        $this->membership($instance, $cup, $other->id, ['group_id' => 2, 'groups_active' => false, 'points' => 9]);
        $roundId = $this->round($this->knockout($instance, $cup), 1, 'final', 'final', 1);
        $tieId = $this->tie($roundId, $instance->club_id, $rival->id);
        $this->match($instance, $cup, $instance->club_id, $rival->id, ['status' => 'completed', 'home_team_goals' => 0, 'away_team_goals' => 1]);
        $this->match($instance, $cup, $instance->club_id, $rival->id, ['knockout_tie_id' => $tieId, 'leg_number' => 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$cup->id)->assertInertia(fn (Assert $page) => $page
            ->has('table', 0)->has('groups', 2)->has('rounds', 1)
            ->where('groups.0.id', 1)->where('groups.0.table.0.club_name', 'Group Leader')
            ->where('groups.0.table.0.position', 1)->where('groups.0.table.1.position', 2)
            ->where('groups.0.table.0.outcomes.0.type', 'knockout')
            ->where('groups.1.id', 2)->where('groups.1.table.0.position', 1)
            ->where('matches.0.group_id', 1)->where('matches.1.group_id', null));
    }

    #[Test]
    public function group_cups_before_the_draw_have_an_empty_state(): void
    {
        $instance = $this->gameWorld();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament', 'groups' => 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$cup->id)->assertInertia(fn (Assert $page) => $page
            ->where('competition.groups', 1)->has('groups', 0)->has('rounds', 0)->has('matches', 0));
    }

    #[Test]
    public function active_group_phases_show_tables_and_group_fixtures_without_a_knockout_draw(): void
    {
        $instance = $this->gameWorld();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament', 'groups' => 1]);
        $rival = Club::factory()->create(['instance_id' => $instance->id]);
        $this->membership($instance, $cup, $instance->club_id, ['group_id' => 1, 'groups_active' => true]);
        $this->membership($instance, $cup, $rival->id, ['group_id' => 1, 'groups_active' => true]);
        $this->match($instance, $cup, $instance->club_id, $rival->id);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$cup->id)->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)->has('groups.0.table', 2)->has('rounds', 0)
            ->where('matches.0.group_id', 1)->where('matches.0.status', 'scheduled'));
    }

    #[Test]
    public function a_completed_cup_includes_its_final_result_and_winner(): void
    {
        $instance = $this->gameWorld();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament']);
        $rival = Club::factory()->create(['instance_id' => $instance->id]);
        $knockoutId = $this->knockout($instance, $cup);
        $roundId = $this->round($knockoutId, 1, 'final', 'final', 1);
        $tieId = $this->tie($roundId, $instance->club_id, $rival->id, $rival->id);
        $this->match($instance, $cup, $instance->club_id, $rival->id, [
            'knockout_tie_id' => $tieId, 'leg_number' => 1, 'status' => 'completed',
            'home_team_goals' => 0, 'away_team_goals' => 1,
        ]);
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id]);
        $oldKnockoutId = DB::table('tournament_knockout')->insertGetId([
            'instance_id' => $instance->id, 'season_id' => $oldSeason->id,
            'competition_id' => $cup->id, 'participant_count' => 4, 'bracket_size' => 4,
        ]);
        $this->round($oldKnockoutId, 1, 'final', 'final', 1);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$cup->id)->assertInertia(fn (Assert $page) => $page
            ->has('rounds', 1)->has('matches', 1)
            ->where('rounds.0.ties.0.winner_club_id', $rival->id)
            ->where('matches.0.home_team_goals', 0)->where('matches.0.away_team_goals', 1));
    }

    #[Test]
    public function unemployed_managers_can_browse_competitions(): void
    {
        $instance = $this->gameWorld();
        $instance->club_id = null;
        $instance->save();
        $cup = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'tournament']);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions')->assertInertia(fn (Assert $page) => $page
            ->where('competition.id', $cup->id)->where('clubId', null));
    }

    #[Test]
    public function a_game_without_competitions_has_an_empty_state(): void
    {
        $instance = $this->gameWorld();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions')->assertInertia(fn (Assert $page) => $page
            ->where('competition', null)->has('competitions', 0)->has('matches', 0)
            ->has('table', 0)->has('groups', 0)->has('rounds', 0));
    }

    #[Test]
    public function competitions_from_other_game_worlds_are_not_accessible(): void
    {
        $instance = $this->gameWorld();
        $other = Competition::factory()->create(['instance_id' => $instance->id + 1]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/'.$other->id)->assertNotFound();
    }

    #[Test]
    public function missing_competitions_return_not_found(): void
    {
        $instance = $this->gameWorld();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/competitions/999999')->assertNotFound();
    }

    private function gameWorld(): Instance
    {
        $instance = Instance::factory()->create(['club_id' => null, 'followed_competition_id' => null]);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-01', 'end_date' => '2027-06-15']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Managed FC']);
        $instance->club_id = $club->id;
        $instance->season_id = $season->id;
        $instance->save();

        return $instance;
    }

    /** @param array<string, int|bool> $attributes */
    private function membership(Instance $instance, Competition $competition, int $clubId, array $attributes = []): void
    {
        DB::table('competition_season')->insert(array_merge([
            'instance_id' => $instance->id, 'season_id' => $instance->season_id,
            'competition_id' => $competition->id, 'club_id' => $clubId,
        ], $attributes));
    }

    /** @param array<string, int|string|null> $attributes */
    private function match(Instance $instance, Competition $competition, int $home, int $away, array $attributes = []): Game
    {
        return Game::factory()->create(array_merge([
            'instance_id' => $instance->id, 'season_id' => $instance->season_id,
            'competition_id' => $competition->id, 'hometeam_id' => $home, 'awayteam_id' => $away,
            'stadium_id' => 1, 'match_start' => '2026-08-15 15:00:00',
        ], $attributes));
    }

    private function knockout(Instance $instance, Competition $competition): int
    {
        return DB::table('tournament_knockout')->insertGetId([
            'instance_id' => $instance->id, 'season_id' => $instance->season_id,
            'competition_id' => $competition->id, 'participant_count' => 4, 'bracket_size' => 4,
        ]);
    }

    private function progressionCompetition(Instance $instance, string $name, string $type = 'league'): Competition
    {
        $baseId = DB::table('base_competitions')->insertGetId([
            'name' => $name, 'type' => $type, 'country_code' => 'GBR', 'rank' => 100, 'clubs_number' => 20,
        ]);

        return Competition::factory()->create([
            'instance_id' => $instance->id, 'base_competition_id' => $baseId,
            'name' => $name, 'type' => $type,
        ]);
    }

    private function round(int $knockoutId, int $number, string $side, string $name, int $legs): int
    {
        return DB::table('tournament_knockout_rounds')->insertGetId([
            'tournament_knockout_id' => $knockoutId, 'round_number' => $number,
            'bracket_side' => $side, 'name' => $name, 'number_of_legs' => $legs,
        ]);
    }

    private function tie(int $roundId, ?int $home = null, ?int $away = null, ?int $winner = null): int
    {
        return DB::table('tournament_knockout_ties')->insertGetId([
            'round_id' => $roundId, 'position' => 1, 'home_club_id' => $home,
            'away_club_id' => $away, 'winner_club_id' => $winner,
            'status' => $winner === null ? 'scheduled' : 'completed',
        ]);
    }
}
