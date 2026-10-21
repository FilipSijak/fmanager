<?php

namespace Tests\Feature;

use App\GameEntityType;
use App\Models\BaseData\BaseGameEntity;
use App\Models\GameEntity;
use App\Models\Instance;
use App\Services\InstanceService\InstanceData\InitialSeed;
use Database\Seeders\GameEntitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameEntitiesSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_eighty_named_sponsors_with_four_at_each_rank(): void
    {
        (new GameEntitiesSeeder)->run();

        $sponsors = BaseGameEntity::query()->where('type', GameEntityType::SPONSOR->value)->get();
        $this->assertCount(80, $sponsors);
        $this->assertCount(80, $sponsors->pluck('name')->unique());
        foreach (range(1, 20) as $rank) {
            $this->assertCount(4, $sponsors->where('rank', $rank));
        }
        $this->assertDatabaseHas('base_game_entities', ['name' => 'Oakfield Bakery', 'rank' => 1]);
        $this->assertDatabaseHas('base_game_entities', ['name' => 'Titan Automotive', 'rank' => 20]);
        foreach (GameEntityType::cases() as $type) {
            $this->assertTrue(BaseGameEntity::query()->where('type', $type->value)->whereBetween('rank', [1, 20])->exists());
        }
    }

    #[Test]
    public function rerunning_the_catalogue_seeder_updates_entries_without_creating_duplicates(): void
    {
        $seeder = new GameEntitiesSeeder;
        $seeder->run();
        BaseGameEntity::query()->where('name', 'Game Bank')->update(['rank' => 1, 'starting_balance' => 1]);

        $seeder->run();

        $this->assertDatabaseCount('base_game_entities', 85);
        $this->assertDatabaseHas('base_game_entities', ['name' => 'Game Bank', 'rank' => 20, 'starting_balance' => 50000000000]);
    }

    #[Test]
    public function each_game_receives_independent_entities_and_accounts_from_the_catalogue(): void
    {
        $baseEntity = BaseGameEntity::factory()->create(['rank' => 13, 'starting_balance' => 123456]);
        $first = Instance::factory()->create();
        $second = Instance::factory()->create();
        $seeder = app(InitialSeed::class);

        $seeder->seedGameEntities($first->id);
        $seeder->seedGameEntities($second->id);

        $firstEntity = GameEntity::query()->where('instance_id', $first->id)->sole();
        $secondEntity = GameEntity::query()->where('instance_id', $second->id)->sole();
        $this->assertSame($baseEntity->name, $firstEntity->name);
        $this->assertSame($baseEntity->name, $secondEntity->name);
        $this->assertSame(13, $firstEntity->rank);
        $this->assertSame(13, $secondEntity->rank);
        $this->assertNotSame($firstEntity->id, $secondEntity->id);
        $this->assertSame(123456, $firstEntity->account->balance);
        $this->assertSame(123456, $firstEntity->account->future_balance);
        $this->assertSame(123456, $secondEntity->account->balance);
        $firstEntity->update(['rank' => 20]);
        $firstEntity->account->update(['balance' => 0]);
        $this->assertSame(13, $baseEntity->fresh()->rank);
        $this->assertSame(13, $secondEntity->fresh()->rank);
        $this->assertSame(123456, $secondEntity->account->fresh()->balance);
        $this->assertDatabaseCount('accounts_game_entities', 2);
    }

    #[Test]
    public function every_game_entity_type_can_store_a_rank(): void
    {
        $instance = Instance::factory()->create();

        foreach (GameEntityType::cases() as $type) {
            $entity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => $type, 'rank' => 17]);
            $this->assertSame(17, $entity->fresh()->rank);
            $this->assertSame($type, $entity->fresh()->type);
        }
    }
}
