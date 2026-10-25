<?php

namespace Tests\Integration\Instance;

use App\Events\MatchDayCompleted;
use App\Events\NextDay;
use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Services\GameService\CompleteGameService;
use App\Services\InstanceService\InstanceService;
use App\Services\PersonService\PersonConfig\PersonTypes;
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
        $this->actingAs(User::factory()->create())->postJson('/api/startNewGame')
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

    #[Test]
    public function next_day_picks_matchday_squads_for_ai_clubs_but_not_the_users_club(): void
    {
        Event::fake([NextDay::class, MatchDayCompleted::class]);
        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        $instance = Instance::factory()->create(['id' => 1, 'season_id' => 1, 'instance_date' => '2026-08-15']);
        app(GameContext::class)->set(1, 1, '2026-08-15');
        $formation = BaseFormation::query()->create(['code' => '4-4-2', 'name' => '4-4-2', 'is_active' => true, 'tactical_tendency' => 'balanced']);
        $positions = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

        foreach ($positions as $index => $position) {
            $formation->slots()->create(['slot' => (string) ($index + 1), 'position' => $position, 'x' => 50, 'y' => 50]);
        }

        [$userClub, $aiClub] = Club::factory()->count(2)->create(['instance_id' => 1])->all();
        $instance->forceFill(['club_id' => $userClub->id])->save();

        foreach ([$userClub, $aiClub] as $club) {
            StaffCoaching::factory()->create(['instance_id' => 1, 'club_id' => $club->id, 'type' => PersonTypes::MANAGER]);

            foreach ($positions as $position) {
                Player::factory()->create(['instance_id' => 1, 'club_id' => $club->id, 'position' => $position]);
            }
        }

        $game = Game::factory()->create([
            'instance_id' => 1,
            'season_id' => 1,
            'competition_id' => Competition::factory()->create(['instance_id' => 1])->id,
            'hometeam_id' => $userClub->id,
            'awayteam_id' => $aiClub->id,
            'stadium_id' => 1,
            'match_start' => '2026-08-15 15:00:00',
        ]);
        $this->mock(CompleteGameService::class)->shouldReceive('complete')->once()->andReturn($game);

        app(InstanceService::class)->nextDay();

        $this->assertSame(11, ClubTacticPlayerSlot::query()->where('club_id', $aiClub->id)->count());
        $this->assertSame(0, ClubTacticPlayerSlot::query()->where('club_id', $userClub->id)->count());
    }
}
