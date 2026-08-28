<?php

namespace Tests\Integration\Instance;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Events\MonthlyUpdate;
use App\Models\Instance;
use App\Models\Season;
use App\Services\InstanceService\InstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MonthlyUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_the_monthly_update_on_the_first_day_of_the_month(): void
    {
        Event::fake();
        $instance = $this->createInstance('2027-03-01');

        app(InstanceService::class)->nextDay();

        Event::assertDispatched(
            MonthlyUpdate::class,
            fn (MonthlyUpdate $event): bool => $event->instance->is($instance),
        );
    }

    #[Test]
    public function it_does_not_dispatch_the_monthly_update_on_other_days(): void
    {
        Event::fake();
        $this->createInstance('2027-03-02');

        app(InstanceService::class)->nextDay();

        Event::assertNotDispatched(MonthlyUpdate::class);
    }

    #[Test]
    public function its_listener_queues_an_instance_player_reindex(): void
    {
        $instance = new Instance;
        $instance->setAttribute('id', 7);
        $dispatcher = $this->mock(PlayerSearchIndexDispatcher::class);
        $dispatcher->shouldReceive('reindexInstance')->once()->with(7);

        event(new MonthlyUpdate($instance));
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
