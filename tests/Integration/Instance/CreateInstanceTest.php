<?php

namespace Tests\Integration\Instance;

use App\Models\BaseData\BaseClubs;
use App\Models\BaseData\BaseCompetitions;
use App\Models\BaseData\BaseStadiums;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Stadium;
use App\Services\InstanceService\InstanceData\InitialSeed;
use Database\Seeders\ClubsSeeder;
use Database\Seeders\CompetitionsSeeder;
use Database\Seeders\StadiumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateInstanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_new_instance()
    {
        $instance = Instance::factory()->create();

        $this->assertDatabaseHas('instances', ['club_id' => $instance->club_id]);
    }

    /** @test */
    public function it_can_copy_base_data()
    {
        (new ClubsSeeder)->run();
        (new StadiumSeeder)->run();
        (new CompetitionsSeeder)->run();
        $instance = Instance::factory()->make(['id' => 1]);

        $initialSeed = new InitialSeed();
        $initialSeed->seedFromBaseTables($instance->id);

        $baseCompetitionsCount = BaseCompetitions::all()->count();
        $competitions = Competition::all();
        $baseStadiumsCount = BaseStadiums::all()->count();
        $stadiums = Stadium::all();
        $baseClubsCount = BaseClubs::all()->count();
        $clubs = Club::all();

        $this->assertEquals($baseCompetitionsCount, $competitions->count());
        $this->assertEquals($baseStadiumsCount, $stadiums->count());
        $this->assertEquals($baseClubsCount, $clubs->count());

        $this->assertDatabaseHas('clubs',
            [
                'name' => $clubs[0]->name,
                'instance_id' => $instance->id,
            ]
        );

        $this->assertDatabaseHas('stadiums',
             [
                 'name' => $stadiums[0]->name,
                 'instance_id' => $instance->id,
             ]
        );

        $this->assertDatabaseHas('competitions',
             [
                 'name' => $competitions[0]->name,
                 'instance_id' => $instance->id,
             ]
        );
    }
}
