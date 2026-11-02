<?php

namespace Tests\Integration\Repositories;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Repositories\TrainingRepository;
use App\Services\TrainingService\Data\ScheduledGameData;
use App\Services\TrainingService\Data\TrainingPlayerData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TrainingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_loads_players_progress_and_current_injuries_together(): void
    {
        $instance = Instance::factory()->create(['id' => 1, 'season_id' => 1]);
        Season::factory()->create(['id' => 1, 'instance_id' => $instance->id]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $player = Player::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'potential' => 100,
            'max_potential' => 150,
            'pace' => 12,
        ]);
        DB::table('players_progress')->where('player_id', $player->id)->update([
            'condition' => 90,
            'pace' => 45,
        ]);
        DB::table('injuries')->insert([
            'id' => 1,
            'type' => 'Knee injury',
            'severity' => 4,
            'duration_from' => 10,
            'duration_to' => 20,
        ]);
        DB::table('player_injuries')->insert([
            'instance_id' => $instance->id,
            'season_id' => 1,
            'player_id' => $player->id,
            'injury_id' => 1,
            'injury_start_date' => '2027-06-01',
            'injury_end_date' => '2027-06-20',
        ]);
        $repository = app(TrainingRepository::class);

        $players = $repository->transaction(
            fn () => $repository->playersForTraining(
                $club,
                ['pace'],
                CarbonImmutable::parse('2027-06-10')
            )
        );

        $this->assertCount(1, $players);
        $this->assertInstanceOf(TrainingPlayerData::class, $players->first());
        $this->assertTrue($players->first()->injured);
        $this->assertSame(12, $players->first()->attribute('pace'));
        $this->assertSame(45, $players->first()->accumulatedProgress('pace'));
        $this->assertSame(90, $players->first()->condition);

        $repository->bulkUpdateProgress([
            $player->id => ['pace' => 70, 'condition' => 91],
        ]);
        $repository->bulkUpdatePlayers([
            $player->id => ['pace' => 13],
        ]);

        $this->assertDatabaseHas('players_progress', [
            'player_id' => $player->id,
            'pace' => 70,
            'condition' => 91,
        ]);
        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'pace' => 13,
        ]);
    }

    #[Test]
    #[TestWith(['2027-06-10', '2027-06-10', true, true])]
    #[TestWith(['2027-06-11', '2027-06-20', true, false])]
    #[TestWith(['2027-06-01', '2027-06-09', true, false])]
    #[TestWith(['2027-06-01', '2027-06-20', false, false])]
    public function injury_status_and_player_availability_share_date_and_instance_constraints(string $start, string $end, bool $sameInstance, bool $injured): void
    {
        [$instance, $club, $player] = $this->trainingPlayer();
        $injuryInstance = $sameInstance ? $instance : Instance::factory()->create();
        DB::table('injuries')->insert(['id' => 1, 'type' => 'Knee injury', 'severity' => 4, 'duration_from' => 1, 'duration_to' => 20]);
        DB::table('player_injuries')->insert([
            'instance_id' => $injuryInstance->id, 'season_id' => $instance->season_id, 'player_id' => $player->id,
            'injury_id' => 1, 'injury_start_date' => $start, 'injury_end_date' => $end,
        ]);
        $repository = app(TrainingRepository::class);

        $overview = $repository->playersForOverview($club, ['pace'], '2027-06-10')->sole();
        $dailyPlayer = $repository->transaction(fn () => $repository->playersForTraining($club, ['pace'], CarbonImmutable::parse('2027-06-10')))->sole();

        $this->assertSame($injured, (bool) $overview->is_injured);
        $this->assertSame($injured, $dailyPlayer->injured);
        $this->assertSame(! $injured, Player::query()->whereKey($player->id)->notInjuredOn('2027-06-10')->exists());
    }

    #[Test]
    public function daily_training_and_recovery_exclude_retired_players_and_other_clubs_or_saves(): void
    {
        [$instance, $club, $player] = $this->trainingPlayer();
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'is_retired' => true]);
        $otherClub = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $otherClub->id]);
        $otherInstance = Instance::factory()->create();
        Player::factory()->create(['instance_id' => $otherInstance->id, 'club_id' => $club->id]);
        $repository = app(TrainingRepository::class);

        $players = $repository->transaction(fn () => $repository->playersForTraining($club, ['pace'], CarbonImmutable::parse('2027-06-10')));

        $this->assertSame([$player->id], $players->pluck('id')->all());
        $this->assertSame([$player->id], $repository->transaction(fn () => $repository->activePlayerIds($club))->all());
    }

    #[Test]
    #[TestWith([0, 180])]
    #[TestWith([120, 120])]
    public function training_data_uses_the_persisted_category_potential_or_its_legacy_fallback(int $currentPotential, int $expected): void
    {
        [, $club, $player] = $this->trainingPlayer();
        $player->forceFill(['technical' => 180, 'current_technical_potential' => $currentPotential])->save();
        $repository = app(TrainingRepository::class);

        $data = $repository->transaction(fn () => $repository->playersForTraining($club, ['pace'], CarbonImmutable::parse('2027-06-10')))->sole();

        $this->assertSame($expected, $data->currentTechnical);
    }

    #[Test]
    public function saving_schedules_preserves_the_original_creation_date_and_updates_existing_rows(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-06-01 12:00:00'));
        [, , $player] = $this->trainingPlayer();
        $this->travelTo(CarbonImmutable::parse('2027-06-10 12:00:00'));

        app(TrainingRepository::class)->saveSchedules([['player_id' => $player->id, 'training_category_id' => 1, 'training_intensity_id' => 3]]);

        $this->assertDatabaseCount('training_player_schedule', 4);
        $this->assertDatabaseHas('training_player_schedule', [
            'player_id' => $player->id, 'training_category_id' => 1, 'training_intensity_id' => 3,
            'created_at' => '2027-06-01 12:00:00', 'updated_at' => '2027-06-10 12:00:00',
        ]);
        $this->assertDatabaseHas('training_player_schedule', ['player_id' => $player->id, 'training_category_id' => 2, 'training_intensity_id' => 2]);
    }

    #[Test]
    public function training_dates_include_both_days_and_exclude_cancelled_games_and_other_saves(): void
    {
        [$instance, $club] = $this->trainingPlayer();
        $opponent = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id]);
        $game = [
            'instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $competition->id,
            'stadium_id' => 100, 'hometeam_id' => $club->id, 'awayteam_id' => $opponent->id, 'status' => Game::STATUS_SCHEDULED,
        ];
        Game::factory()->create($game + ['match_start' => '2027-06-09 00:00:00']);
        Game::factory()->create($game + ['match_start' => '2027-06-10 23:59:59']);
        Game::factory()->create($game + ['match_start' => '2027-06-11 00:00:00']);
        Game::factory()->create([...$game, 'match_start' => '2027-06-10 12:00:00', 'status' => Game::STATUS_CANCELLED]);
        $otherInstance = Instance::factory()->create();
        Game::factory()->create([...$game, 'match_start' => '2027-06-10 12:00:00', 'instance_id' => $otherInstance->id]);

        $games = app(TrainingRepository::class)->scheduledGames($instance->id, CarbonImmutable::parse('2027-06-09'), CarbonImmutable::parse('2027-06-10'));

        $this->assertSame(['2027-06-09 00:00:00', '2027-06-10 23:59:59'], $games->map(fn (ScheduledGameData $game): string => $game->kickoff->toDateTimeString())->sort()->values()->all());
    }

    /** @return array{Instance, Club, Player} */
    private function trainingPlayer(): array
    {
        $instance = Instance::factory()->create();
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id])->save();
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100]);
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id]);

        return [$instance, $club, $player];
    }
}
