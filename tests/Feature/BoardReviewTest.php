<?php

namespace Tests\Feature;

use App\Events\BoardReviewDue;
use App\Events\MatchDayCompleted;
use App\Events\NextDay;
use App\Events\SeasonCompleted;
use App\Models\Club;
use App\Models\ClubBoardReview;
use App\Models\ClubSeasonExpectation;
use App\Models\Competition;
use App\Models\CompetitionProgressionRule;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\News;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\StaffContract;
use App\Services\ManagerJobService\ManagerJobService;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class BoardReviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function early_reviews_allow_extra_underperformance_without_treating_the_relegation_zone_as_confirmed_relegation(): void
    {
        [$instance, $expectation, $manager] = $this->world('2030-10-15', 18);
        $this->relegationRule($expectation, 3);

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'position' => 18, 'effective_tolerance' => 8, 'outcome' => 'retained']);
        $this->assertSame($expectation->club_id, $manager->fresh()->club_id);
        $this->assertSame(4, $expectation->fresh()->tolerance);
        $this->assertDatabaseHas('news', ['instance_id' => $instance->id, 'club_id' => $expectation->club_id, 'type' => 'board_review', 'published_at' => '2030-10-15 00:00:00']);
    }

    #[Test]
    public function a_late_failed_review_ends_the_contract_and_leaves_the_user_unemployed_with_a_visible_report(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 18);
        $contractId = $manager->contract_id;

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'effective_tolerance' => 5, 'outcome' => 'dismissed']);
        $this->assertDatabaseHas('staff_coaching', ['id' => $manager->id, 'club_id' => null, 'contract_id' => null]);
        $this->assertDatabaseMissing('staff_contracts', ['id' => $contractId]);
        $this->assertDatabaseHas('instances', ['id' => $instance->id, 'club_id' => null, 'manager_id' => $manager->id, 'followed_competition_id' => $expectation->competition_id]);
        $this->assertDatabaseHas('managers', ['instance_id' => $instance->id, 'staff_coaching_id' => $manager->id]);
        $this->assertDatabaseHas('news', ['instance_id' => $instance->id, 'club_id' => null, 'competition_id' => null, 'type' => 'board_review']);
    }

    #[Test]
    public function final_reviews_use_base_tolerance_and_never_tolerate_relegation(): void
    {
        [$instance, $expectation] = $this->world('2031-06-15', 16);
        $this->relegationRule($expectation, 5);

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'effective_tolerance' => 4, 'outcome' => 'dismissed']);
        $this->assertNull($instance->fresh()->club_id);
    }

    #[Test]
    public function too_few_played_matches_defer_judgement_without_dismissal(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 20, 4);

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'outcome' => 'deferred', 'reason' => 'Fewer than five league matches have been played.']);
        $this->assertSame($expectation->club_id, $manager->fresh()->club_id);
    }

    #[Test]
    public function newly_appointed_managers_have_two_months_before_being_judged(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 20);
        $manager->contract->forceFill(['contract_start' => '2031-03-01'])->save();

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'outcome' => 'deferred', 'reason' => 'The manager has been in charge for less than two months.']);
        $this->assertSame($expectation->club_id, $manager->fresh()->club_id);
    }

    #[Test]
    public function ai_dismissals_do_not_change_the_managed_club_or_send_ai_reports_to_the_user(): void
    {
        [$instance, $expectation] = $this->world('2031-04-15', 12);
        $clubId = DB::table('competition_season')->where('competition_id', $expectation->competition_id)->orderBy('points')->value('club_id');
        $contract = StaffContract::factory()->create(['contract_start' => '2030-07-01', 'contract_end' => '2032-06-15']);
        $manager = StaffCoaching::factory()->create(['instance_id' => $instance->id, 'club_id' => $clubId, 'contract_id' => $contract->id]);
        $other = ClubSeasonExpectation::factory()->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'club_id' => $clubId, 'competition_id' => $expectation->competition_id, 'target_position' => 12, 'league_size' => 20]);

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $other->id, 'outcome' => 'dismissed']);
        $this->assertNull($manager->fresh()->club_id);
        $this->assertSame($expectation->club_id, $instance->fresh()->club_id);
        $this->assertSame(1, News::query()->where('type', 'board_review')->count());
    }

    #[Test]
    public function repeating_a_review_date_does_not_duplicate_the_outcome_or_news(): void
    {
        [$instance] = $this->world('2031-04-15', 20);

        $this->review($instance);
        $this->review($instance);

        $this->assertDatabaseCount('club_board_reviews', 1);
        $this->assertSame(1, News::query()->where('type', 'board_review')->count());
    }

    #[Test]
    public function dismissed_user_managers_cannot_immediately_apply_back_to_the_same_club(): void
    {
        [$instance, $expectation] = $this->world('2031-04-15', 20);
        $this->review($instance);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->from('/jobs')->post('/jobs/'.$expectation->club_id.'/apply')
            ->assertSessionHasErrors(['application' => 'This club will not reconsider a manager it dismissed in the last two months.']);

        $this->assertDatabaseCount('manager_job_applications', 0);
    }

    #[Test]
    public function the_vacancy_service_does_not_rehire_the_ai_manager_that_club_just_dismissed(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 20);
        Manager::query()->where('staff_coaching_id', $manager->id)->delete();
        $instance->forceFill(['club_id' => null, 'manager_id' => null])->save();
        $manager->forceFill(['reputation' => $expectation->club->managerReputationTarget()])->save();
        $this->review($instance);

        app(ManagerJobService::class)->fillVacancies($instance->fresh());

        $this->assertNotSame($expectation->club_id, $manager->fresh()->club_id);
        $this->assertDatabaseHas('club_board_reviews', ['staff_coaching_id' => $manager->id, 'outcome' => 'dismissed']);
    }

    #[Test]
    public function season_rollover_does_not_allow_immediate_reappointment_after_a_final_review(): void
    {
        [$instance, $expectation] = $this->world('2031-06-15', 20);
        $this->review($instance);
        $nextSeason = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->refresh()->forceFill(['season_id' => $nextSeason->id, 'instance_date' => '2031-06-16'])->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->from('/jobs')->post('/jobs/'.$expectation->club_id.'/apply')
            ->assertSessionHasErrors(['application' => 'This club will not reconsider a manager it dismissed in the last two months.']);
    }

    #[Test]
    public function the_reappointment_restriction_expires_after_two_months(): void
    {
        [$instance, $expectation] = $this->world('2031-04-15', 20);
        $this->review($instance);
        $instance->refresh()->forceFill(['instance_date' => '2031-06-15'])->save();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->from('/jobs')->post('/jobs/'.$expectation->club_id.'/apply')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('manager_job_applications', ['instance_id' => $instance->id, 'club_id' => $expectation->club_id, 'staff_coaching_id' => $instance->manager_id]);
    }

    #[Test]
    public function reviews_are_isolated_from_other_saves_and_ambient_game_context(): void
    {
        [$instance] = $this->world('2031-04-15', 20);
        [$foreign, $foreignExpectation, $foreignManager] = $this->world('2031-04-15', 20);
        app(GameContext::class)->set($foreign->id, $foreign->season_id, $foreign->instance_date);

        $this->review($instance);

        $this->assertSame($foreignExpectation->club_id, $foreignManager->fresh()->club_id);
        $this->assertSame($foreignExpectation->club_id, $foreign->fresh()->club_id);
        $this->assertSame(0, ClubBoardReview::query()->forInstance($foreign->id)->count());
        $this->assertSame(0, News::query()->forInstance($foreign->id)->where('type', 'board_review')->count());
    }

    #[Test]
    public function off_schedule_events_do_not_review_anyone(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-16', 20);

        $this->review($instance);

        $this->assertDatabaseCount('club_board_reviews', 0);
        $this->assertSame($expectation->club_id, $manager->fresh()->club_id);
    }

    #[Test]
    public function day_advancement_preserves_dismissal_when_it_saves_the_next_date(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 18);
        Event::fake([NextDay::class]);

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)
            ->postJson('/api/instance/next-day')->assertOk()->assertJsonPath('data.date', '2031-04-16');

        Event::assertDispatched(NextDay::class);
        $this->assertDatabaseHas('instances', ['id' => $instance->id, 'club_id' => null, 'manager_id' => $manager->id, 'instance_date' => '2031-04-16']);
        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'reviewed_on' => '2031-04-15', 'outcome' => 'dismissed']);
    }

    #[Test]
    public function a_failed_report_rolls_back_dismissal_contract_deletion_and_the_review_marker(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 20);
        $contractId = $manager->contract_id;
        $event = 'eloquent.creating: '.News::class;
        Event::listen($event, function (): void {
            throw new RuntimeException('Report failed.');
        });

        try {
            $this->review($instance);
            $this->fail('Expected the report to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Report failed.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('club_board_reviews', 0);
        $this->assertSame($expectation->club_id, $instance->fresh()->club_id);
        $this->assertDatabaseHas('staff_coaching', ['id' => $manager->id, 'club_id' => $expectation->club_id, 'contract_id' => $contractId]);
        $this->assertDatabaseHas('staff_contracts', ['id' => $contractId]);

        $this->review($instance);

        $this->assertDatabaseCount('club_board_reviews', 1);
        $this->assertNull($instance->fresh()->club_id);
    }

    #[Test]
    public function incomplete_league_tables_defer_judgement(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 20);
        DB::table('competition_season')->where('competition_id', $expectation->competition_id)->where('club_id', '!=', $expectation->club_id)->delete();

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'outcome' => 'deferred']);
        $this->assertSame($expectation->club_id, $manager->fresh()->club_id);
    }

    #[Test]
    public function vacant_clubs_have_a_review_record_without_a_dismissal(): void
    {
        [$instance, $expectation, $manager] = $this->world('2031-04-15', 20);
        $manager->forceFill(['club_id' => null])->save();

        $this->review($instance);

        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'outcome' => 'vacant', 'staff_coaching_id' => null]);
    }

    #[Test]
    #[TestWith(['2030-10-15', 1])]
    #[TestWith(['2030-10-16', 0])]
    #[TestWith(['2030-11-15', 0])]
    #[TestWith(['2030-12-15', 1])]
    #[TestWith(['2031-06-15', 1])]
    public function day_advancement_dispatches_reviews_on_the_expected_dates(string $date, int $count): void
    {
        [$instance] = $this->world($date, 12);
        Event::fake([NextDay::class, BoardReviewDue::class, SeasonCompleted::class]);

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)->postJson('/api/instance/next-day')->assertOk();

        Event::assertDispatched(NextDay::class);
        Event::assertDispatchedTimes(BoardReviewDue::class, $count);
    }

    #[Test]
    public function matchday_processing_precedes_the_final_review_and_its_date_does_not_advance_with_the_instance(): void
    {
        [$instance, $expectation] = $this->world('2031-06-15', 12);
        $otherClubId = DB::table('competition_season')->where('competition_id', $expectation->competition_id)->where('club_id', '!=', $expectation->club_id)->value('club_id');
        Game::factory()->create([
            'instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $expectation->competition_id,
            'hometeam_id' => $expectation->club_id, 'awayteam_id' => $otherClubId, 'stadium_id' => 100,
            'match_start' => '2031-06-15 12:00:00', 'status' => Game::STATUS_COMPLETED, 'processed_at' => '2031-06-15 14:00:00',
            'home_team_goals' => 1, 'away_team_goals' => 0,
        ]);
        Event::fake([NextDay::class, SeasonCompleted::class]);
        $order = [];
        Event::listen(MatchDayCompleted::class, function () use (&$order): void {
            $order[] = 'matches';
        });
        Event::listen(BoardReviewDue::class, function () use (&$order): void {
            $order[] = 'review';
        });

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)->postJson('/api/instance/next-day')->assertOk();

        $this->assertSame(['matches', 'review'], $order);
        Event::assertDispatched(NextDay::class);
        Event::assertDispatched(SeasonCompleted::class);
        $this->assertDatabaseHas('club_board_reviews', ['club_season_expectation_id' => $expectation->id, 'reviewed_on' => '2031-06-15']);
        $this->assertSame('2031-06-16', $instance->fresh()->instance_date);
    }

    private function review(Instance $instance): void
    {
        event(new BoardReviewDue($instance, $instance->season_id, CarbonImmutable::parse($instance->instance_date)));
    }

    /** @return array{Instance, ClubSeasonExpectation, StaffCoaching} */
    private function world(string $date, int $position, int $played = 8): array
    {
        $instance = Instance::factory()->create(['instance_date' => $date]);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-15', 'end_date' => '2031-06-15']);
        $league = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league', 'competition_scope' => 'domestic']);
        $clubs = Club::factory()->count(20)->create(['instance_id' => $instance->id, 'stadium_id' => 100]);
        foreach ($clubs as $index => $club) {
            DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $league->id, 'club_id' => $club->id, 'played' => $played, 'points' => 100 - $index]);
        }
        $club = $clubs[$position - 1];
        $contract = StaffContract::factory()->create(['contract_start' => '2030-07-01', 'contract_end' => '2032-06-15']);
        $manager = StaffCoaching::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'contract_id' => $contract->id]);
        $instance->forceFill(['season_id' => $season->id, 'club_id' => $club->id, 'manager_id' => $manager->id])->save();
        Manager::factory()->create(['instance_id' => $instance->id, 'user_id' => $instance->user_id, 'staff_coaching_id' => $manager->id]);
        $expectation = ClubSeasonExpectation::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'club_id' => $club->id, 'competition_id' => $league->id, 'target_position' => 12, 'league_size' => 20]);

        return [$instance, $expectation, $manager];
    }

    private function relegationRule(ClubSeasonExpectation $expectation, int $places): void
    {
        $source = DB::table('base_competitions')->insertGetId(['name' => 'Upper league', 'country_code' => 'GB', 'rank' => 20, 'type' => 'league', 'clubs_number' => 20]);
        $target = DB::table('base_competitions')->insertGetId(['name' => 'Lower league', 'country_code' => 'GB', 'rank' => 10, 'type' => 'league', 'clubs_number' => 20]);
        $expectation->competition->forceFill(['base_competition_id' => $source])->save();
        CompetitionProgressionRule::query()->create(['source_base_competition_id' => $source, 'target_base_competition_id' => $target, 'progression_type' => 'relegation', 'selector_type' => 'bottom_positions', 'places' => $places, 'active' => true]);
    }
}
