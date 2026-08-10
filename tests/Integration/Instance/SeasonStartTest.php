<?php

namespace Tests\Integration\Instance;

use App\Events\SeasonStarted;
use App\Models\Instance;
use App\Models\Season;
use App\Services\InstanceService\InstanceService;
use App\Services\SeasonService\SeasonStartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeasonStartTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_the_season_started_event_on_june_sixteenth(): void
    {
        Event::fake([SeasonStarted::class]);

        $instance = $this->createInstance('2027-06-16');

        app(InstanceService::class)->nextDay();

        Event::assertDispatched(
            SeasonStarted::class,
            fn (SeasonStarted $event): bool => $event->instance->is($instance)
        );
    }

    #[Test]
    public function it_does_not_dispatch_the_season_started_event_on_other_dates(): void
    {
        Event::fake([SeasonStarted::class]);

        $this->createInstance('2027-06-17');

        app(InstanceService::class)->nextDay();

        Event::assertNotDispatched(SeasonStarted::class);
    }

    #[Test]
    public function its_listener_calls_the_season_start_service(): void
    {
        $instance = $this->createInstance('2027-06-16');
        $service = $this->mock(SeasonStartService::class);

        $service->shouldReceive('start')
            ->once()
            ->with($instance);

        event(new SeasonStarted($instance));
    }

    private function createInstance(string $date): Instance
    {
        Season::factory()->create([
            'id' => 1,
            'instance_id' => 1,
        ]);

        return Instance::factory()->create([
            'id' => 1,
            'season_id' => 1,
            'instance_date' => $date,
        ]);
    }
}
