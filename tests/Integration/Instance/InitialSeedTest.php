<?php

namespace Tests\Integration\Instance;

use App\GameEntityType;
use App\Models\BaseData\BaseStadiumStandCapacityLimit;
use App\Models\Club;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Stadium;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\StadiumStandStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InitialSeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_normalized_stadium_capacities_and_active_stands(): void
    {
        (new DatabaseSeeder)->run();
        $instance = Instance::factory()->create();

        app(InitialSeed::class)->seedStadiumsFromBaseTable($instance->id);

        $stadiums = Stadium::query()->where('instance_id', $instance->id)->with('stands')->get();

        $this->assertNotEmpty($stadiums);

        foreach ($stadiums as $stadium) {
            $limits = BaseStadiumStandCapacityLimit::query()
                ->where('stadium_type', $stadium->type->value)
                ->pluck('maximum_capacity', 'position');

            $this->assertSame($limits->count(), $stadium->stands->count());
            $this->assertSame($stadium->capacity, $stadium->active_capacity);
            $this->assertSame(
                $stadium->capacity,
                $stadium->stands->sum(fn ($stand): int => (int) $stand->capacity),
            );

            foreach ($stadium->stands as $stand) {
                $this->assertLessThanOrEqual(
                    (int) $limits->get($stand->position->value),
                    (int) $stand->capacity,
                );
                $this->assertSame(0, $stand->capacity % 1000);

                if ($stand->capacity > 0) {
                    $this->assertSame(StadiumStandStatus::ACTIVE, $stand->status);
                }
            }
        }
    }

    #[Test]
    public function it_links_each_instance_club_to_that_instances_stadium(): void
    {
        (new DatabaseSeeder)->run();
        $firstInstance = Instance::factory()->create();
        $secondInstance = Instance::factory()->create();

        app(InitialSeed::class)->seedFromBaseTables($firstInstance->id);
        app(InitialSeed::class)->seedFromBaseTables($secondInstance->id);

        $clubs = Club::query()->where('instance_id', $secondInstance->id)->with('stadium')->get();

        $this->assertNotEmpty($clubs);

        foreach ($clubs as $club) {
            $this->assertSame($secondInstance->id, $club->stadium->instance_id);
        }
    }

    #[Test]
    public function it_seeds_game_entities_with_their_initial_account_balances(): void
    {
        (new DatabaseSeeder)->run();
        $instance = Instance::factory()->create();

        app(InitialSeed::class)->seedFromBaseTables($instance->id);

        $accounts = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->with('gameEntity')
            ->get()
            ->keyBy(fn (GameEntityAccount $account): string => $account->gameEntity->type->value);

        $this->assertCount(5, $accounts);
        $this->assertSame(50_000_000_000, $accounts->get(GameEntityType::BANK->value)->balance);

        foreach (GameEntityType::cases() as $type) {
            $account = $accounts->get($type->value);

            $this->assertNotNull($account);
            $this->assertSame(
                $type === GameEntityType::BANK ? 50_000_000_000 : 10_000_000_000,
                $account->balance,
            );
            $this->assertSame($account->balance, $account->future_balance);
        }
    }
}
