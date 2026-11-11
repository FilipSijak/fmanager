<?php

namespace Tests\Feature;

use App\Jobs\ReindexInstancePlayers;
use App\Models\Club;
use App\Models\Instance;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Services\ClubService\ClubService;
use App\Services\NewsService\NewsService;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\SeasonService\SeasonService;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AnnualYouthIntakeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function season_start_gives_each_club_young_players_with_contracts_positions_and_training_records(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11'));
        [$instance, $club, $season] = $this->world('2090-06-16');
        $otherClub = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100, 'rank' => 5, 'rank_academy' => 5, 'country_code' => 'ES']);
        $otherInstance = Instance::factory()->create();
        $foreignClub = Club::factory()->create(['instance_id' => $otherInstance->id, 'stadium_id' => 100]);
        Queue::fake([ReindexInstancePlayers::class]);

        app(SeasonService::class)->start($instance);

        foreach ([$club, $otherClub] as $intakeClub) {
            $players = Player::query()->forClub($intakeClub)->with(['person', 'contract'])->get();
            $this->assertCount(3, $players);

            foreach ($players as $player) {
                $age = (int) CarbonImmutable::parse($player->person->dob)->diffInYears(CarbonImmutable::parse('2090-06-16'));
                $this->assertGreaterThanOrEqual(16, $age);
                $this->assertLessThanOrEqual(18, $age);
                $this->assertSame($intakeClub->country_code, $player->person->country_code);
                $this->assertSame($instance->id, $player->person->instance_id);
                $this->assertSame('2090-06-16', $player->contract->contract_start);
                $this->assertGreaterThan('2090-06-16', $player->contract->contract_end);
                $this->assertGreaterThan(0, (int) $player->contract->salary);
                $this->assertDatabaseHas('player_position', [
                    'player_id' => $player->id,
                    'position_id' => array_search($player->position, PlayerPositionConfig::PLAYER_POSITIONS, true),
                ]);
                $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'instance_id' => $instance->id]);
                $this->assertDatabaseHas('training_player_schedule', ['player_id' => $player->id]);
            }
        }

        $this->assertSame(0, Player::query()->forClub($foreignClub)->count());
        $this->assertSame('2090-06-16', $season->fresh()->youth_intake_completed_on);
        $news = News::query()->where('type', 'youth_intake')->sole();
        $this->assertSame($club->id, $news->club_id);
        $this->assertSame($season->id, $news->season_id);
        $this->assertSame('2090-06-16', $news->published_at->toDateString());
        foreach (Player::query()->forClub($club)->with('person')->get() as $player) {
            $this->assertStringContainsString($player->person->first_name.' '.$player->person->last_name, $news->content);
            $this->assertStringContainsString($player->position, $news->content);
        }
        Queue::assertPushed(ReindexInstancePlayers::class, fn (ReindexInstancePlayers $job): bool => $job->instanceId === $instance->id && $job->afterCommit === true);
    }

    #[Test]
    public function repeating_an_intake_does_not_duplicate_players_contracts_or_news(): void
    {
        [$instance] = $this->world();
        Queue::fake([ReindexInstancePlayers::class]);
        $service = app(ClubService::class);
        $service->annualYouthIntake($instance);

        $service->annualYouthIntake($instance);

        $this->assertDatabaseCount('players', 3);
        $this->assertDatabaseCount('people', 3);
        $this->assertDatabaseCount('players_contracts', 3);
        $this->assertDatabaseCount('players_progress', 3);
        $this->assertSame(1, News::query()->where('type', 'youth_intake')->count());
        Queue::assertPushed(ReindexInstancePlayers::class, 1);
    }

    #[Test]
    public function a_new_season_receives_another_intake(): void
    {
        [$instance] = $this->world();
        Queue::fake([ReindexInstancePlayers::class]);
        app(ClubService::class)->annualYouthIntake($instance);
        $nextSeason = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2028-08-15', 'end_date' => '2029-06-15']);
        $instance->forceFill(['season_id' => $nextSeason->id, 'instance_date' => '2028-06-16'])->save();

        app(ClubService::class)->annualYouthIntake($instance);

        $this->assertDatabaseCount('players', 6);
        $this->assertSame('2028-06-16', $nextSeason->fresh()->youth_intake_completed_on);
        $this->assertSame(2, News::query()->where('type', 'youth_intake')->count());
        Queue::assertPushed(ReindexInstancePlayers::class, 2);
    }

    #[Test]
    public function intake_uses_the_supplied_instance_even_when_game_context_points_elsewhere(): void
    {
        [$instance, $club] = $this->world();
        $otherInstance = Instance::factory()->create();
        app(GameContext::class)->set($otherInstance->id, 999, '2040-01-01');
        Queue::fake([ReindexInstancePlayers::class]);

        app(ClubService::class)->annualYouthIntake($instance);

        $this->assertSame(3, Player::query()->forClub($club)->count());
        $this->assertSame(0, Player::query()->forInstance($otherInstance->id)->count());
        $this->assertDatabaseHas('players_contracts', ['contract_start' => '2027-06-16']);
        Queue::assertPushed(ReindexInstancePlayers::class, fn (ReindexInstancePlayers $job): bool => $job->instanceId === $instance->id);
    }

    #[Test]
    public function unemployed_managers_still_receive_world_intakes_without_a_club_report(): void
    {
        [$instance, $club] = $this->world();
        $instance->forceFill(['club_id' => null])->save();
        Queue::fake([ReindexInstancePlayers::class]);

        app(ClubService::class)->annualYouthIntake($instance);

        $this->assertSame(3, Player::query()->forClub($club)->count());
        $this->assertSame(0, News::query()->where('type', 'youth_intake')->count());
        Queue::assertPushed(ReindexInstancePlayers::class, 1);
    }

    #[Test]
    public function a_failed_intake_rolls_back_players_contracts_positions_progress_and_the_completion_marker(): void
    {
        [$instance, , $season] = $this->world();
        Queue::fake([ReindexInstancePlayers::class]);
        $this->mock(NewsService::class)->shouldReceive('publish')->once()->andThrow(new RuntimeException('Report failed.'));

        try {
            app(ClubService::class)->annualYouthIntake($instance);
            $this->fail('Expected intake report creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Report failed.', $exception->getMessage());
        }

        foreach (['players', 'people', 'players_contracts', 'player_position', 'players_progress', 'training_player_schedule', 'news'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull($season->fresh()->youth_intake_completed_on);
        Queue::assertNotPushed(ReindexInstancePlayers::class);
    }

    #[Test]
    public function a_world_without_clubs_completes_without_dispatching_a_search_reindex(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2027-06-16']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id])->save();
        Queue::fake([ReindexInstancePlayers::class]);

        app(ClubService::class)->annualYouthIntake($instance);

        $this->assertSame('2027-06-16', $season->fresh()->youth_intake_completed_on);
        $this->assertDatabaseCount('players', 0);
        Queue::assertNotPushed(ReindexInstancePlayers::class);
    }

    #[Test]
    public function a_season_from_another_instance_cannot_receive_an_intake(): void
    {
        [$instance, , $season] = $this->world();
        $otherInstance = Instance::factory()->create();
        $otherSeason = Season::factory()->create(['instance_id' => $otherInstance->id]);
        $instance->forceFill(['season_id' => $otherSeason->id])->save();
        Queue::fake([ReindexInstancePlayers::class]);

        try {
            app(ClubService::class)->annualYouthIntake($instance);
            $this->fail('Expected a season from another instance to be rejected.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Season::class, $exception->getModel());
        }

        $this->assertDatabaseCount('players', 0);
        $this->assertNull($season->fresh()->youth_intake_completed_on);
        $this->assertNull($otherSeason->fresh()->youth_intake_completed_on);
        Queue::assertNotPushed(ReindexInstancePlayers::class);
    }

    /** @return array{Instance, Club, Season} */
    private function world(string $date = '2027-06-16'): array
    {
        $instance = Instance::factory()->create(['instance_date' => $date]);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => substr($date, 0, 4).'-08-15', 'end_date' => ((int) substr($date, 0, 4) + 1).'-06-15']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100, 'rank' => 10, 'rank_academy' => 10, 'country_code' => 'GB']);
        $instance->forceFill(['season_id' => $season->id, 'club_id' => $club->id])->save();

        return [$instance, $club, $season];
    }
}
