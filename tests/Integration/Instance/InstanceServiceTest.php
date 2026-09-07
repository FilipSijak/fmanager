<?php

namespace Tests\Integration\Instance;

use App\Events\NextDay;
use App\Models\Instance;
use App\Models\Season;
use App\Services\InstanceService\InstanceService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstanceServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_next_day_with_the_current_instance(): void
    {
        Event::fake([NextDay::class]);

        Season::factory()->create([
            'id' => 1,
            'instance_id' => 1,
        ]);
        $other = Instance::factory()->create(['id' => 1, 'instance_date' => '2024-03-02']);
        $instance = Instance::factory()->create([
            'id' => 2,
            'season_id' => 1,
            'instance_date' => '2024-03-02',
        ]);

        app(GameContext::class)->set(2, 1, '2024-03-02');
        app()->make(InstanceService::class)->nextDay();

        $this->assertSame('2024-03-03', $instance->fresh()->instance_date);
        $this->assertSame('2024-03-02', $other->fresh()->instance_date);

        Event::assertDispatched(
            NextDay::class,
            fn (NextDay $event): bool => $event->instance->is($instance)
        );
    }

    #[Test]
    public function it_requires_an_explicit_instance(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Game context instance id has not been set.');
        app(InstanceService::class)->nextDay();
    }

    #[Test]
    public function failed_creation_rolls_back_the_instance(): void
    {
        $this->postJson('/api/startNewGame')
            ->assertStatus(500)
            ->assertJsonPath('error', 'Failed to create new instance');
        $this->assertDatabaseCount('instances', 0);
        $this->assertDatabaseCount('seasons', 0);
    }

    #[Test]
    public function get_cannot_create_a_game(): void
    {
        $this->getJson('/api/startNewGame')->assertStatus(405);
        $this->assertDatabaseCount('instances', 0);
    }
}
