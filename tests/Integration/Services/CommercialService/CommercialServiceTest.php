<?php

namespace Tests\Integration\Services\CommercialService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Stadium;
use App\Services\CommercialService\CommercialService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommercialServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_ticket_prices_for_all_current_competitions_of_a_club(): void
    {
        app(GameContext::class)->set(1, 1);
        Instance::factory()->create(['id' => 1, 'season_id' => 1]);
        $stadium = Stadium::factory()->create(['instance_id' => 1, 'ticket_price' => 42]);
        DB::table('countries')->insert([
            'code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100,
            'population' => 60000000, 'gdp' => 2883887, 'gdpcapita' => 42000, 'continent' => 'Europe',
        ]);
        DB::table('clubs')->insert([
            'id' => 10, 'name' => 'Arsenal', 'instance_id' => 1, 'country_code' => 'GBR',
            'stadium_id' => $stadium->id, 'rank' => 20, 'rank_academy' => 20, 'rank_training' => 20, 'financial_rank' => 20,
        ]);
        DB::table('competitions')->insert([
            ['id' => 20, 'instance_id' => 1, 'name' => 'Champions League', 'country_code' => 'EU', 'rank' => 10000, 'type' => 'tournament', 'groups' => 1, 'clubs_number' => 32],
            ['id' => 21, 'instance_id' => 1, 'name' => 'Domestic Cup', 'country_code' => 'GBR', 'rank' => 7000, 'type' => 'tournament', 'groups' => 0, 'clubs_number' => 32],
        ]);
        DB::table('competition_season')->insert([
            ['instance_id' => 1, 'season_id' => 1, 'competition_id' => 20, 'club_id' => 10, 'group_id' => 1],
            ['instance_id' => 1, 'season_id' => 1, 'competition_id' => 20, 'club_id' => 10, 'group_id' => 2],
            ['instance_id' => 1, 'season_id' => 1, 'competition_id' => 21, 'club_id' => 10, 'group_id' => null],
        ]);
        $club = (new Club)->forceFill(['id' => 10, 'instance_id' => 1, 'country_code' => 'GBR', 'rank' => 20, 'stadium_id' => $stadium->id]);

        $prices = app(CommercialService::class)->ticketPricesForClub($club);

        $this->assertSame([20 => 42, 21 => 42], $prices->all());
    }
}
