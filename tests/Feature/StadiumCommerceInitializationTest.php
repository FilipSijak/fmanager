<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\CommercialService;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\MatchdayService\MatchdayService;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumCommerceInitializationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function initialization_stores_a_ticket_price_and_copies_products_only_for_built_venue_categories(): void
    {
        [$instance, $stadium] = $this->world();
        $shop = BaseCommercialCategory::query()->create(['slug' => 'shop', 'name' => 'Shop']);
        BaseCommercialProducts::query()->forceCreate(['slug' => 'scarf', 'name' => 'Scarf', 'category_id' => $shop->id, 'base_price' => 1200]);

        app(CommercialService::class)->initializeStadiums($instance->id);

        $this->assertSame(100, $stadium->fresh()->ticket_price);
        $this->assertDatabaseCount('stadium_commercial_products', 2);
        $this->assertDatabaseHas('stadium_commercial_products', ['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'base_price' => 450, 'price_change_coef' => 1, 'is_available' => true]);
        $this->assertDatabaseMissing('stadium_commercial_products', ['stadium_id' => $stadium->id, 'base_price' => 1200]);
    }

    #[Test]
    public function rerunning_initialization_preserves_custom_prices_free_admission_and_disabled_products(): void
    {
        [, $stadium, , , $category] = $this->world();
        $commercial = app(CommercialService::class);
        $commercial->initializeStadium($stadium);
        $stadium->forceFill(['ticket_price' => 0])->save();
        $product = StadiumCommercialProduct::query()->forStadium($stadium)->orderBy('id')->firstOrFail();
        $product->forceFill(['base_price' => 600, 'price_change_coef' => 1.25, 'is_available' => false])->save();
        BaseCommercialProducts::query()->whereKey($product->base_product_id)->update(['base_price' => 900]);
        $water = BaseCommercialProducts::query()->forceCreate(['slug' => 'water', 'name' => 'Water', 'category_id' => $category->id, 'base_price' => 125]);

        $commercial->initializeStadium($stadium);
        $commercial->initializeStadium($stadium);

        $this->assertSame(0, $stadium->fresh()->ticket_price);
        $this->assertSame(600, $product->fresh()->base_price);
        $this->assertSame('1.2500', $product->fresh()->price_change_coef);
        $this->assertFalse($product->fresh()->is_available);
        $this->assertDatabaseCount('stadium_commercial_products', 3);
        $this->assertDatabaseHas('stadium_commercial_products', ['stadium_id' => $stadium->id, 'base_product_id' => $water->id, 'base_price' => 125, 'is_available' => true]);
    }

    #[Test]
    public function building_a_venue_immediately_initializes_its_products_and_the_stadium_ticket_price(): void
    {
        [, $stadium, , , $category] = $this->world(false);
        DB::table('base_commercial_category_stadium_type')->insert(['category_id' => $category->id, 'stadium_type' => 'local']);
        foreach ([1, 2, 3] as $size) {
            DB::table('base_commercial_venue_costs')->insert(['category_id' => $category->id, 'size' => $size, 'base_cost' => 100000]);
        }

        $venue = app(BuildCommercialVenue::class)->handle($stadium, $category->id, CommercialVenueSize::SMALL);

        $this->assertSame($stadium->id, $venue->stadium_id);
        $this->assertSame(100, $stadium->fresh()->ticket_price);
        $this->assertDatabaseCount('stadium_commercial_products', 2);
    }

    #[Test]
    public function the_backfill_command_initializes_only_the_requested_game_and_can_be_repeated(): void
    {
        [$instance, $stadium] = $this->world();
        [, $foreignStadium] = $this->world();

        $this->artisan('commercial:initialize-stadiums', ['--instance' => (string) $instance->id])
            ->expectsOutput('Initialized 1 stadiums.')->assertSuccessful();
        $this->artisan('commercial:initialize-stadiums', ['--instance' => (string) $instance->id])->assertSuccessful();

        $this->assertSame(100, $stadium->fresh()->ticket_price);
        $this->assertNull($foreignStadium->fresh()->ticket_price);
        $this->assertDatabaseCount('stadium_commercial_products', 2);
        $this->assertDatabaseMissing('stadium_commercial_products', ['stadium_id' => $foreignStadium->id]);
    }

    #[Test]
    public function the_backfill_command_can_initialize_all_existing_games(): void
    {
        [, $firstStadium] = $this->world();
        [, $secondStadium] = $this->world();

        $this->artisan('commercial:initialize-stadiums')->expectsOutput('Initialized 2 stadiums.')->assertSuccessful();

        $this->assertSame(100, $firstStadium->fresh()->ticket_price);
        $this->assertSame(100, $secondStadium->fresh()->ticket_price);
        $this->assertDatabaseCount('stadium_commercial_products', 4);
    }

    #[Test]
    public function invalid_and_unknown_instance_options_do_not_backfill_every_game(): void
    {
        [, $stadium] = $this->world();

        $this->artisan('commercial:initialize-stadiums', ['--instance' => 'invalid'])->assertFailed();
        $this->artisan('commercial:initialize-stadiums', ['--instance' => '999999'])->assertFailed();

        $this->assertNull($stadium->fresh()->ticket_price);
        $this->assertDatabaseCount('stadium_commercial_products', 0);
    }

    #[Test]
    public function an_existing_stadium_without_products_can_earn_commercial_income_on_its_next_match(): void
    {
        [$instance, $stadium, $home, $competition] = $this->world();
        $away = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 20]);
        Account::factory()->create(['club_id' => $home->id]);
        $game = Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $competition->id,
            'hometeam_id' => $home->id, 'awayteam_id' => $away->id, 'stadium_id' => $stadium->id,
            'status' => Game::STATUS_COMPLETED, 'attendance' => null, 'match_start' => '2030-01-07 15:00:00']);

        $settlement = app(MatchdayService::class)->settle($game);

        $this->assertSame(100, $stadium->fresh()->ticket_price);
        $this->assertSame(100, $settlement->ticket_price);
        $this->assertSame(100000, $settlement->gate_receipts);
        $this->assertSame(700, $settlement->commercial_income);
        $this->assertDatabaseCount('stadium_commercial_products', 2);
    }

    /** @return array{Instance, Stadium, Club, Competition, BaseCommercialCategory} */
    private function world(bool $withVenue = true): array
    {
        $instance = Instance::factory()->create();
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id])->save();
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id, 'capacity' => 1000, 'active_capacity' => 1000, 'country_code' => 'GBR', 'type' => 'local']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'rank' => 20, 'country_code' => 'GBR']);
        if (! Country::query()->where('code', 'GBR')->exists()) {
            Country::query()->forceCreate(['code' => 'GBR', 'name' => 'United Kingdom', 'ranking' => 100, 'population' => 60000000]);
        }
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'rank' => 10000, 'type' => 'league']);
        DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $season->id, 'club_id' => $club->id, 'competition_id' => $competition->id]);
        $category = BaseCommercialCategory::query()->firstOrCreate(['slug' => 'bar'], ['name' => 'Bar']);
        if (! BaseCommercialProducts::query()->where('category_id', $category->id)->exists()) {
            BaseCommercialProducts::query()->forceCreate(['slug' => 'lager', 'name' => 'Lager', 'category_id' => $category->id, 'base_price' => 450]);
            BaseCommercialProducts::query()->forceCreate(['slug' => 'coffee', 'name' => 'Coffee', 'category_id' => $category->id, 'base_price' => 250]);
        }
        if ($withVenue) {
            StadiumCommercialVenue::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id, 'category_id' => $category->id, 'size' => 1]);
        }

        return [$instance, $stadium, $club, $competition, $category];
    }
}
