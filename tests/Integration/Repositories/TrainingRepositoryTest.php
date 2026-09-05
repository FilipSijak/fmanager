<?php

namespace Tests\Integration\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Repositories\TrainingRepository;
use App\Services\TrainingService\Data\TrainingPlayerData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
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
}
