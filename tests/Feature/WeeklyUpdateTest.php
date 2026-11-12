<?php

namespace Tests\Feature;

use App\Events\NextDay;
use App\Events\WeeklyUpdate;
use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Season;
use App\Services\InstanceService\InstanceService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function monday_progression_pays_wages_on_the_game_date_before_advancing_the_day(): void
    {
        $instance = $this->createInstance('2030-01-07');
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $account = Account::factory()->create(['club_id' => $club->id, 'balance' => 10000, 'future_balance' => 10000]);
        $contract = PlayerContract::factory()->create(['salary' => 1000, 'contract_start' => '2029-07-01', 'contract_end' => '2030-01-07']);
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'contract_id' => $contract->id]);
        Event::fakeExcept([WeeklyUpdate::class]);

        app(InstanceService::class)->nextDay();

        $this->assertSame('2030-01-08', $instance->fresh()->instance_date);
        $this->assertSame(9000, $account->fresh()->balance);
        $this->assertDatabaseHas('finance_transactions', ['sending_account_id' => $account->id, 'amount' => 1000, 'transaction_date' => '2030-01-07 00:00:00']);
        $this->assertDatabaseHas('club_payroll_runs', ['club_id' => $club->id, 'paid_on' => '2030-01-07']);
        Event::assertDispatched(NextDay::class, fn (NextDay $event): bool => $event->instance->is($instance));
    }

    #[Test]
    #[DataProvider('otherWeekdays')]
    public function other_weekdays_do_not_dispatch_a_weekly_update(string $date): void
    {
        $instance = $this->createInstance($date);
        Event::fake();

        app(InstanceService::class)->nextDay();

        Event::assertNotDispatched(WeeklyUpdate::class);
    }

    /** @return list<array{string}> */
    public static function otherWeekdays(): array
    {
        return [['2030-01-08'], ['2030-01-09'], ['2030-01-10'], ['2030-01-11'], ['2030-01-12'], ['2030-01-13']];
    }

    private function createInstance(string $date): Instance
    {
        $instance = Instance::factory()->create(['instance_date' => $date]);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id])->save();
        app(GameContext::class)->set($instance->id, $season->id, $date);

        return $instance;
    }
}
