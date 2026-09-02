<?php

namespace Tests\Unit\Services\TrainingService;

use App\Services\TrainingService\PlayerProgress;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProgressTest extends TestCase
{
    #[Test]
    public function an_injured_player_loses_accumulated_progress_without_player_updates(): void
    {
        $player = (object) ['is_injured' => true];
        $progress = (object) ['pace' => 1];

        $updates = (new PlayerProgress)->forTrainingSession(
            $player,
            $progress,
            new Collection,
            ['pace']
        );

        $this->assertSame(0, $updates->progress['pace']);
        $this->assertSame([], $updates->player);
    }

    #[Test]
    public function a_player_without_scheduled_training_recovers_condition(): void
    {
        $player = (object) [
            'is_injured' => false,
            'potential' => 100,
            'max_potential' => 150,
        ];
        $progress = (object) ['condition' => 95];

        $updates = (new PlayerProgress)->forTrainingSession($player, $progress, null, []);

        $this->assertSame(98, $updates->progress['condition']);
    }

    #[Test]
    public function rest_recovery_is_capped_at_full_condition(): void
    {
        $this->assertSame(100, (new PlayerProgress)->recoveredCondition(95));
    }
}
