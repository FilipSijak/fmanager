<?php

namespace Tests\Integration\Services\SeasonService;

use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Models\Season;
use App\Services\SeasonService\PlayerRetirement;
use App\Services\SeasonService\SeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeasonStartPlayerDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function season_start_recalculates_potential_and_reduces_attributes_as_a_player_ages(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'season_id' => 1,
            'instance_date' => '2030-06-16',
        ]);
        Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
        ]);
        $person = Person::factory()->create([
            'instance_id' => $instance->id,
            'dob' => '2006-06-16',
        ]);
        $player = Player::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'club_id' => null,
            'max_potential' => 180,
            'potential' => 180,
        ]);
        $player->forceFill([
            'technical' => 180,
            'mental' => 180,
            'physical' => 180,
            'marking' => 20,
            'positioning' => 20,
            'strength' => 20,
        ])->save();

        $this->mock(PlayerRetirement::class)
            ->shouldReceive('retireEligiblePlayers')
            ->andReturn([]);

        $seasonService = app(SeasonService::class);
        $seasonService->start($instance);
        $atAge24 = $player->fresh();

        $instance->forceFill(['instance_date' => '2035-06-16'])->save();
        $seasonService->start($instance->fresh());
        $atAge29 = $player->fresh();

        $this->assertSame(180, (int) $atAge24->potential);
        $this->assertSame(180, (int) $atAge24->current_technical_potential);
        $this->assertSame(180, (int) $atAge24->current_mental_potential);
        $this->assertSame(180, (int) $atAge24->current_physical_potential);
        $this->assertSame(18, (int) $atAge24->marking);
        $this->assertSame(18, (int) $atAge24->positioning);
        $this->assertSame(18, (int) $atAge24->strength);

        $this->assertSame(176, (int) $atAge29->potential);
        $this->assertSame(178, (int) $atAge29->current_technical_potential);
        $this->assertSame(178, (int) $atAge29->current_mental_potential);
        $this->assertSame(176, (int) $atAge29->current_physical_potential);
        $this->assertSame(17, (int) $atAge29->marking);
        $this->assertSame(17, (int) $atAge29->positioning);
        $this->assertSame(17, (int) $atAge29->strength);

        $instance->forceFill(['instance_date' => '2044-06-16'])->save();
        $seasonService->start($instance->fresh());
        $atAge38 = $player->fresh();

        $this->assertSame(135, (int) $atAge38->potential);
        $this->assertSame(162, (int) $atAge38->current_technical_potential);
        $this->assertSame(158, (int) $atAge38->current_mental_potential);
        $this->assertSame(112, (int) $atAge38->current_physical_potential);
        $this->assertSame(16, (int) $atAge38->marking);
        $this->assertSame(15, (int) $atAge38->positioning);
        $this->assertSame(11, (int) $atAge38->strength);
    }
}
