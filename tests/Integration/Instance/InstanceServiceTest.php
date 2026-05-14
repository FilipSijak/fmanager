<?php

namespace Tests\Integration\Instance;

use App\Events\NextDay;
use App\Models\Instance;
use App\Models\Season;
use App\Services\InstanceService\InstanceService;
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
        $instance = Instance::factory()->create([
            'id' => 1,
            'season_id' => 1,
            'instance_date' => '2024-03-01',
        ]);

        app()->make(InstanceService::class)->nextDay();

        Event::assertDispatched(
            NextDay::class,
            fn (NextDay $event): bool => $event->instance->is($instance)
        );
    }
}
