<?php

namespace Tests\Feature;

use App\Jobs\ReindexInstancePlayers;
use App\Models\Account;
use App\Models\BoardExpectationType;
use App\Models\Club;
use App\Models\ClubCompetitionProgression;
use App\Models\ClubSeasonExpectation;
use App\Models\Competition;
use App\Models\CompetitionProgressionRule;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Services\ClubService\ClubService;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\SeasonService\SeasonService;
use App\Support\GameContext;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class SeasonExpectationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function expectations_use_league_resources_and_snapshot_the_selected_type_and_inputs(): void
    {
        [$instance, $league] = $this->world();
        $strong = $this->club($instance, $league, 3000000, 18);
        $middle = $this->club($instance, $league, 2000000, 10);
        $weak = $this->club($instance, $league, 1000000, 2);
        $foreign = Instance::factory()->create();
        app(GameContext::class)->set($foreign->id, 999, '2040-01-01');

        app(ClubService::class)->setSeasonExpectations($instance);

        $this->assertDatabaseCount('club_season_expectations', 3);
        $this->assertDatabaseHas('club_season_expectations', [
            'instance_id' => $instance->id, 'season_id' => $instance->season_id, 'club_id' => $strong->id,
            'competition_id' => $league->id, 'description' => 'Win the league', 'rating' => 100, 'tolerance' => 2,
            'expectation_score' => 100, 'target_position' => 1, 'league_size' => 3,
            'budget' => 3000000, 'transfer_budget' => 3000000, 'team_quality' => 18, 'club_rank' => 18,
            'assigned_on' => '2030-07-01',
        ]);
        $this->assertDatabaseHas('club_season_expectations', ['club_id' => $middle->id, 'expectation_score' => 50, 'rating' => 40, 'tolerance' => 4, 'target_position' => 2]);
        $this->assertDatabaseHas('club_season_expectations', ['club_id' => $weak->id, 'expectation_score' => 0, 'rating' => 20]);
        $this->assertFalse(ClubSeasonExpectation::query()->forInstance($foreign->id)->exists());
        $expectation = $strong->seasonExpectations()->sole();
        $this->assertSame('Win the league', $expectation->type->description);
        $this->assertTrue($expectation->club->is($strong));
        $this->assertSame($instance->season_id, $expectation->season->id);
        $this->assertTrue($expectation->competition->is($league));
    }

    #[Test]
    public function retries_preserve_targets_even_if_the_inputs_and_type_definitions_change(): void
    {
        [$instance, $league] = $this->world();
        $club = $this->club($instance, $league, 1000000, 10);
        $service = app(ClubService::class);
        $service->setSeasonExpectations($instance);
        $before = ClubSeasonExpectation::sole()->getAttributes();
        $club->account->forceFill(['balance' => -1000, 'transfer_budget' => 0])->save();
        $club->forceFill(['rank' => 20])->save();
        BoardExpectationType::query()->update(['description' => 'Changed', 'tolerance' => 0]);

        $service->setSeasonExpectations($instance);

        $this->assertDatabaseCount('club_season_expectations', 1);
        $this->assertSame($before, ClubSeasonExpectation::sole()->getAttributes());
    }

    #[Test]
    public function a_new_season_gets_new_targets_without_changing_history(): void
    {
        [$instance, $league] = $this->world();
        $club = $this->club($instance, $league, 1000000, 10);
        $service = app(ClubService::class);
        $service->setSeasonExpectations($instance);
        $before = ClubSeasonExpectation::sole()->getAttributes();
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id, 'instance_date' => '2031-06-16', 'club_id' => null])->save();
        DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $league->id, 'club_id' => $club->id]);
        $club->account->forceFill(['balance' => 9000000])->save();

        $service->setSeasonExpectations($instance);

        $this->assertDatabaseCount('club_season_expectations', 2);
        $this->assertSame($before, ClubSeasonExpectation::findOrFail($before['id'])->getAttributes());
        $this->assertDatabaseHas('club_season_expectations', ['season_id' => $season->id, 'club_id' => $club->id, 'budget' => 9000000, 'assigned_on' => '2031-06-16']);
    }

    #[Test]
    public function other_leagues_and_saves_do_not_affect_a_clubs_expectations(): void
    {
        [$instance, $league] = $this->world();
        $club = $this->club($instance, $league, 1000000, 10);
        $otherLeague = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league']);
        $this->club($instance, $otherLeague, 999999999, 20);
        [$foreign, $foreignLeague] = $this->world();
        $foreignClub = $this->club($foreign, $foreignLeague, 999999999, 20);
        DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $league->id, 'club_id' => $foreignClub->id]);

        app(ClubService::class)->setSeasonExpectations($instance);

        $this->assertDatabaseHas('club_season_expectations', ['club_id' => $club->id, 'expectation_score' => 50, 'league_size' => 1]);
        $this->assertDatabaseCount('club_season_expectations', 2);
        $this->assertFalse(ClubSeasonExpectation::query()->where('club_id', $foreignClub->id)->exists());
    }

    #[Test]
    public function squad_quality_uses_the_strongest_eleven_active_players_and_current_loan_membership(): void
    {
        [$instance, $league] = $this->world();
        $club = $this->club($instance, $league, 1000000, 10);
        Player::query()->where('club_id', $club->id)->delete();
        $this->players($instance, $club, 10, 10);
        $this->players($instance, $club, 1, 1);
        $otherClub = $this->club($instance, $league, 1000000, 10);
        $borrowed = $this->players($instance, $otherClub, 20, 1)->sole();
        $borrowed->forceFill(['loan_club_id' => $club->id])->save();
        $retired = $this->players($instance, $club, 20, 1)->sole();
        $retired->forceFill(['is_retired' => true])->save();
        $loanedOut = $this->players($instance, $club, 20, 1)->sole();
        $loanedOut->forceFill(['loan_club_id' => $otherClub->id])->save();

        app(ClubService::class)->setSeasonExpectations($instance);

        $this->assertDatabaseHas('club_season_expectations', ['club_id' => $club->id, 'team_quality' => 10.91]);
    }

    #[Test]
    public function clubs_without_a_league_account_or_squad_still_receive_a_stored_expectation(): void
    {
        [$instance] = $this->world();
        $club = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 5]);

        app(ClubService::class)->setSeasonExpectations($instance);

        $this->assertDatabaseHas('club_season_expectations', [
            'club_id' => $club->id, 'competition_id' => null, 'target_position' => null, 'league_size' => null,
            'budget' => 0, 'transfer_budget' => 0, 'team_quality' => 0, 'expectation_score' => 50,
        ]);
    }

    #[Test]
    public function season_start_assigns_expectations_after_updating_the_squads(): void
    {
        [$instance, $league] = $this->world();
        $club = $this->club($instance, $league, 1000000, 10);
        for ($index = 0; $index < 3; $index++) {
            $this->club($instance, $league, 1000000, 10);
        }
        $instance->forceFill(['club_id' => $club->id, 'instance_date' => '2030-06-16'])->save();
        Queue::fake([ReindexInstancePlayers::class]);

        app(SeasonService::class)->start($instance);

        $this->assertDatabaseHas('club_season_expectations', ['club_id' => $club->id, 'season_id' => $instance->season_id, 'assigned_on' => '2030-06-16']);
        Queue::assertPushed(ReindexInstancePlayers::class);
    }

    #[Test]
    public function assignment_rolls_back_all_clubs_on_failure_and_can_be_retried(): void
    {
        [$instance, $league] = $this->world();
        $first = $this->club($instance, $league, 1000000, 10);
        $second = $this->club($instance, $league, 2000000, 15);
        $event = 'eloquent.creating: '.ClubSeasonExpectation::class;
        Event::listen($event, function (ClubSeasonExpectation $expectation) use ($second): void {
            if ($expectation->club_id === $second->id) {
                throw new RuntimeException('Assignment failed.');
            }
        });

        try {
            app(ClubService::class)->setSeasonExpectations($instance);
            $this->fail('Expected assignment to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Assignment failed.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('club_season_expectations', 0);

        app(ClubService::class)->setSeasonExpectations($instance);

        $this->assertDatabaseCount('club_season_expectations', 2);
        $this->assertSame(1, $first->seasonExpectations()->count());
    }

    #[Test]
    public function a_season_from_another_save_cannot_receive_expectations(): void
    {
        [$instance, $league] = $this->world();
        $this->club($instance, $league, 1000000, 10);
        [$foreign] = $this->world();
        $instance->forceFill(['season_id' => $foreign->season_id])->save();

        try {
            app(ClubService::class)->setSeasonExpectations($instance);
            $this->fail('Expected another saves season to be rejected.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Season::class, $exception->getModel());
        }

        $this->assertDatabaseCount('club_season_expectations', 0);
    }

    #[Test]
    #[TestWith([1, true])]
    #[TestWith([2, true])]
    #[TestWith([3, true])]
    #[TestWith([4, false])]
    public function a_title_target_allows_underperformance_only_within_its_stored_tolerance(int $position, bool $accepted): void
    {
        $expectation = ClubSeasonExpectation::factory()->create(['rating' => 100, 'target_position' => 1, 'league_size' => 20, 'tolerance' => 2]);

        $this->assertSame($accepted, $expectation->acceptsLeaguePosition($position));
    }

    #[Test]
    #[TestWith([12, true])]
    #[TestWith([16, true])]
    #[TestWith([17, false])]
    public function mid_table_targets_tolerate_four_places_below_the_target(int $position, bool $accepted): void
    {
        $expectation = ClubSeasonExpectation::factory()->create(['target_position' => 12, 'league_size' => 20]);

        $this->assertSame($accepted, $expectation->acceptsLeaguePosition($position));
    }

    #[Test]
    #[TestWith(['bottom_positions', 15, true])]
    #[TestWith(['bottom_positions', 16, false])]
    #[TestWith(['position_range', 15, true])]
    #[TestWith(['position_range', 16, false])]
    public function relegation_positions_are_not_tolerated_even_when_within_four_places(string $selector, int $position, bool $accepted): void
    {
        [$expectation] = $this->relegationExpectation($selector);

        $this->assertSame($accepted, $expectation->acceptsLeaguePosition($position));
    }

    #[Test]
    #[TestWith(['inactive'])]
    #[TestWith(['promotion'])]
    #[TestWith(['other_league'])]
    public function unrelated_or_inactive_progression_rules_do_not_reject_a_safe_finish(string $reason): void
    {
        [$expectation, $rule] = $this->relegationExpectation();
        $rule->update(match ($reason) {
            'inactive' => ['active' => false],
            'promotion' => ['progression_type' => 'promotion'],
            'other_league' => ['source_base_competition_id' => $rule->target_base_competition_id],
        });

        $this->assertTrue($expectation->acceptsLeaguePosition(16));
    }

    #[Test]
    #[TestWith(['pending'])]
    #[TestWith(['applied'])]
    public function recorded_relegation_overrides_a_numerically_acceptable_finish(string $status): void
    {
        [$expectation, $rule] = $this->relegationExpectation(places: 3);
        $target = Competition::factory()->create(['instance_id' => $expectation->instance_id, 'base_competition_id' => $rule->target_base_competition_id]);
        ClubCompetitionProgression::query()->create([
            'rule_id' => $rule->id, 'instance_id' => $expectation->instance_id,
            'source_season_id' => $expectation->season_id, 'club_id' => $expectation->club_id,
            'source_competition_id' => $expectation->competition_id, 'target_competition_id' => $target->id,
            'progression_type' => 'relegation', 'source_position' => 16, 'status' => $status,
        ]);

        $this->assertFalse($expectation->acceptsLeaguePosition(16));
    }

    #[Test]
    public function relegation_from_a_previous_season_does_not_affect_the_current_expectation(): void
    {
        [$expectation, $rule] = $this->relegationExpectation(places: 3);
        $previousSeason = Season::factory()->create(['instance_id' => $expectation->instance_id]);
        $target = Competition::factory()->create(['instance_id' => $expectation->instance_id, 'base_competition_id' => $rule->target_base_competition_id]);
        ClubCompetitionProgression::query()->create([
            'rule_id' => $rule->id, 'instance_id' => $expectation->instance_id,
            'source_season_id' => $previousSeason->id, 'club_id' => $expectation->club_id,
            'source_competition_id' => $expectation->competition_id, 'target_competition_id' => $target->id,
            'progression_type' => 'relegation', 'source_position' => 16, 'status' => 'applied',
        ]);

        $this->assertTrue($expectation->acceptsLeaguePosition(16));
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([21])]
    public function out_of_range_finishing_positions_are_rejected(int $position): void
    {
        $expectation = ClubSeasonExpectation::factory()->create(['target_position' => 1, 'league_size' => 20]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The finishing position must belong to this league.');

        $expectation->acceptsLeaguePosition($position);
    }

    #[Test]
    public function a_club_without_a_league_target_cannot_be_judged_on_its_finishing_position(): void
    {
        $expectation = ClubSeasonExpectation::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This expectation has no league position target.');

        $expectation->acceptsLeaguePosition(1);
    }

    #[Test]
    public function missing_type_configuration_fails_without_assigning_partial_expectations(): void
    {
        [$instance, $league] = $this->world();
        $this->club($instance, $league, 1000000, 10);
        BoardExpectationType::query()->delete();

        try {
            app(ClubService::class)->setSeasonExpectations($instance);
            $this->fail('Expected missing expectation types to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Board expectation types must be configured before assigning season expectations.', $exception->getMessage());
        }

        $this->assertDatabaseCount('club_season_expectations', 0);
    }

    #[Test]
    public function an_empty_world_needs_no_expectation_records(): void
    {
        [$instance] = $this->world();

        app(ClubService::class)->setSeasonExpectations($instance);

        $this->assertDatabaseCount('club_season_expectations', 0);
    }

    /** @return array{ClubSeasonExpectation, CompetitionProgressionRule} */
    private function relegationExpectation(string $selector = 'bottom_positions', int $places = 5): array
    {
        [$instance, $league] = $this->world();
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $sourceBaseId = DB::table('base_competitions')->insertGetId([
            'name' => 'Upper league', 'country_code' => 'GB', 'rank' => 20, 'type' => 'league', 'clubs_number' => 20,
        ]);
        $targetBaseId = DB::table('base_competitions')->insertGetId([
            'name' => 'Lower league', 'country_code' => 'GB', 'rank' => 10, 'type' => 'league', 'clubs_number' => 20,
        ]);
        $league->forceFill(['base_competition_id' => $sourceBaseId])->save();
        $rule = CompetitionProgressionRule::query()->create([
            'source_base_competition_id' => $sourceBaseId, 'target_base_competition_id' => $targetBaseId,
            'progression_type' => 'relegation', 'selector_type' => $selector,
            'places' => $places, 'position_from' => 16, 'position_to' => 18, 'active' => true,
        ]);
        $expectation = ClubSeasonExpectation::factory()->create([
            'instance_id' => $instance->id, 'season_id' => $instance->season_id, 'club_id' => $club->id,
            'competition_id' => $league->id, 'target_position' => 12, 'league_size' => 20,
        ]);

        return [$expectation, $rule];
    }

    /** @return array{Instance, Competition} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-07-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id])->save();
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league', 'competition_scope' => 'domestic']);

        return [$instance, $league];
    }

    private function club(Instance $instance, Competition $league, int $budget, int $quality): Club
    {
        $club = Club::factory()->create([
            'instance_id' => $instance->id, 'rank' => $quality, 'rank_academy' => 10,
            'stadium_id' => 100, 'country_code' => 'GB',
        ]);
        Account::factory()->create(['club_id' => $club->id, 'balance' => $budget, 'transfer_budget' => $budget]);
        DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $league->id, 'club_id' => $club->id]);
        $this->players($instance, $club, $quality, 1);

        return $club;
    }

    /** @return Collection<int, Player> */
    private function players(Instance $instance, Club $club, int $quality, int $count): Collection
    {
        return Player::factory()->count($count)->create([
            'instance_id' => $instance->id, 'club_id' => $club->id,
            ...array_fill_keys([...PlayerFields::TECHNICAL_FIELDS, ...PlayerFields::MENTAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS, ...PlayerFields::GOALKEEPING_FIELDS], $quality),
        ]);
    }
}
