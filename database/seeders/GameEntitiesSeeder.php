<?php

namespace Database\Seeders;

use App\GameEntityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameEntitiesSeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['type' => GameEntityType::BANK->value, 'name' => 'Game Bank', 'rank' => 20, 'starting_balance' => 50_000_000_000],
            ['type' => GameEntityType::LOAN_SHARKS->value, 'name' => 'Loan sharks', 'rank' => 10, 'starting_balance' => 1_000_000_000],
            ['type' => GameEntityType::LEAGUE->value, 'name' => 'League Authority', 'rank' => 20, 'starting_balance' => 10_000_000_000],
            ['type' => GameEntityType::TV_BROADCASTER->value, 'name' => 'TV Broadcaster', 'rank' => 20, 'starting_balance' => 10_000_000_000],
            ['type' => GameEntityType::COMPETITION->value, 'name' => 'Competition Authority', 'rank' => 20, 'starting_balance' => 10_000_000_000],
        ];
        $sponsorsByRank = [
            1 => ['Oakfield Bakery', 'Village Garage', 'Greenlane Grocers', 'Riverside Builders'],
            2 => ['Meadow Plumbing', 'Cornerstone Cafe', 'Brookside Butchers', 'Townsend Carpentry'],
            3 => ['Hillcrest Motors', 'Parkside Hardware', 'Elmwood Kitchens', 'Fairway Garden Centre'],
            4 => ['Redbrick Roofing', 'Northgate Tyres', 'Willowbrook Foods', 'Harbour Printworks'],
            5 => ['Valley Transport', 'Summit Workwear', 'Westbridge Homes', 'Copperfield Engineering'],
            6 => ['Beacon Security', 'Eastwood Packaging', 'Cedar Travel', 'Clearwater Windows'],
            7 => ['Granite Construction', 'Lakeside Brewing', 'Silverline Couriers', 'Pioneer Office Supplies'],
            8 => ['Northstar Logistics', 'Meridian Kitchens', 'Bridgewater Textiles', 'Evergreen Energy'],
            9 => ['Horizon Insurance', 'Crownfield Retail', 'Sterling Tools', 'Compass Telecom'],
            10 => ['Vanguard Foods', 'Bluecrest Hotels', 'Keystone Finance', 'Ardent Sportswear'],
            11 => ['Ironbridge Industries', 'Skyline Mobility', 'Helix Healthcare', 'Aspire Electronics'],
            12 => ['Fortis Banking', 'Seabrook Shipping', 'Vector Software', 'Orion Beverages'],
            13 => ['Highland Aerospace', 'Novara Automotive', 'Prism Entertainment', 'Arcadia Resorts'],
            14 => ['Vertex Communications', 'Cobalt Energy', 'Solara Pharmaceuticals', 'Broadstone Capital'],
            15 => ['Nexora Technologies', 'Pacific Horizon Airways', 'Stratos Sports', 'Avalon Consumer Goods'],
            16 => ['Trident Motors', 'Aurelia Banking', 'Sentinel Cybersecurity', 'Polaris Media'],
            17 => ['Omnira Electronics', 'Monarch Energy', 'Echelon Aviation', 'Fusion Digital'],
            18 => ['Centurion Finance', 'Nebula Networks', 'Dominion Automotive', 'Lumina Global'],
            19 => ['Quantum Industries', 'Sovereign Airways', 'Infinity Technologies', 'Olympian Sportswear'],
            20 => ['Apex Global', 'Atlas Technologies', 'Zenith Airways', 'Titan Automotive'],
        ];

        foreach ($sponsorsByRank as $rank => $names) {
            foreach ($names as $name) {
                $entities[] = [
                    'type' => GameEntityType::SPONSOR->value,
                    'name' => $name,
                    'rank' => $rank,
                    'starting_balance' => 10_000_000_000,
                ];
            }
        }

        DB::table('base_game_entities')->upsert(
            $entities,
            ['name'],
            ['type', 'rank', 'starting_balance'],
        );
    }
}
