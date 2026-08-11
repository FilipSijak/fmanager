<?php

namespace Tests\Integration\Instance;

use App\Events\SeasonCompleted;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Progression\SeasonProgressionService;
use App\Services\InstanceService\InstanceService;
use App\Services\SeasonService\SeasonCompletion;
use App\Services\SeasonService\SeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeasonCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_the_season_completed_event_on_june_fifteenth(): void
    {
        Event::fake([SeasonCompleted::class]);

        $instance = $this->createInstance('2027-06-15');

        app(InstanceService::class)->nextDay();

        Event::assertDispatched(
            SeasonCompleted::class,
            fn (SeasonCompleted $event): bool => $event->instance->is($instance)
        );
    }

    #[Test]
    public function it_does_not_dispatch_the_season_completed_event_on_other_dates(): void
    {
        Event::fake([SeasonCompleted::class]);

        $this->createInstance('2027-06-14');

        app(InstanceService::class)->nextDay();

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    #[Test]
    public function its_listener_calls_the_season_completion_service(): void
    {
        $instance = $this->createInstance('2027-06-15');
        $service = $this->mock(SeasonService::class);

        $service->shouldReceive('complete')
            ->once()
            ->with($instance);

        event(new SeasonCompleted($instance));
    }

    #[Test]
    public function the_season_completion_service_calculates_and_applies_progression(): void
    {
        $instance = $this->createInstance('2027-06-15');
        $nextSeason = Season::factory()->create([
            'id' => 2,
            'instance_id' => 1,
            'start_date' => '2027-08-15',
            'end_date' => '2028-06-15',
        ]);

        $progression = $this->mock(SeasonProgressionService::class);
        $progression->shouldReceive('finalize')->once()->with(1);

        $repository = $this->mock(CompetitionRepository::class);
        $repository->shouldReceive('applyCompetitionProgressions')
            ->once()
            ->with(1, 1)
            ->andReturn($nextSeason);

        app(SeasonCompletion::class)->process($instance);

        $this->assertSame(2, $instance->fresh()->season_id);
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
