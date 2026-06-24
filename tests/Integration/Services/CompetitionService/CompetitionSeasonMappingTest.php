<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompetitionSeasonMappingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_maps_initial_competition_season_rows_using_base_ids_not_names(): void
    {
        DB::table('base_competitions')->insert([
            [
                'id' => 100,
                'name' => 'Base League Name',
                'country_code' => 'GBR',
                'rank' => 1,
                'type' => 'league',
                'groups' => 0,
                'clubs_number' => 20,
            ],
        ]);

        DB::table('base_clubs')->insert([
            [
                'id' => 200,
                'name' => 'Base Club Name',
                'country_code' => 'GBR',
                'city_id' => 1,
                'stadium_id' => 1,
                'rank' => 1,
                'rank_academy' => 1,
                'rank_training' => 1,
                'competition_id' => 100,
            ],
        ]);

        DB::table('competitions')->insert([
            [
                'id' => 300,
                'instance_id' => 10,
                'name' => 'Renamed Instance League',
                'country_code' => 'GBR',
                'rank' => 1,
                'type' => 'league',
                'groups' => 0,
                'clubs_number' => 20,
                'base_competition_id' => 100,
            ],
            [
                'id' => 301,
                'instance_id' => 10,
                'name' => 'Base League Name',
                'country_code' => 'GBR',
                'rank' => 1,
                'type' => 'league',
                'groups' => 0,
                'clubs_number' => 20,
                'base_competition_id' => null,
            ],
        ]);

        DB::table('clubs')->insert([
            [
                'id' => 400,
                'name' => 'Renamed Instance Club',
                'instance_id' => 10,
                'country_code' => 'GBR',
                'city_id' => 1,
                'stadium_id' => 1,
                'rank' => 1,
                'rank_academy' => 1,
                'rank_training' => 1,
                'base_club_id' => 200,
            ],
            [
                'id' => 401,
                'name' => 'Base Club Name',
                'instance_id' => 10,
                'country_code' => 'GBR',
                'city_id' => 1,
                'stadium_id' => 1,
                'rank' => 1,
                'rank_academy' => 1,
                'rank_training' => 1,
                'base_club_id' => null,
            ],
        ]);

        (new CompetitionDataSource())->storeInitialCompetitionSeasonClubs(10, 20);

        $this->assertDatabaseHas('competition_season', [
            'instance_id' => 10,
            'season_id' => 20,
            'competition_id' => 300,
            'club_id' => 400,
            'points' => 0,
        ]);

        $this->assertDatabaseMissing('competition_season', [
            'competition_id' => 301,
            'club_id' => 401,
        ]);
    }
}
