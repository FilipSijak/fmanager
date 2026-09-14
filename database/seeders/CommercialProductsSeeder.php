<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommercialProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['slug' => 'shop', 'name' => 'Shop', 'description' => 'Retail products and club merchandise'],
            ['slug' => 'bar', 'name' => 'Bar', 'description' => 'Drinks and refreshments'],
            ['slug' => 'cafe', 'name' => 'Cafe', 'description' => 'Coffee, tea, and light refreshments'],
            ['slug' => 'food_kiosk', 'name' => 'Food Kiosk', 'description' => 'Quick matchday food'],
            ['slug' => 'restaurant', 'name' => 'Restaurant', 'description' => 'Casual dining'],
            ['slug' => 'casino', 'name' => 'Casino', 'description' => 'Casino entertainment'],
            ['slug' => 'hotel', 'name' => 'Hotel', 'description' => 'Stadium hotel accommodation'],
            ['slug' => 'vip_hospitality', 'name' => 'VIP Hospitality', 'description' => 'Premium hospitality experiences'],
            ['slug' => 'fine_dining', 'name' => 'Fine Dining', 'description' => 'High-end dining experiences'],
            ['slug' => 'events_venue', 'name' => 'Events Venue', 'description' => 'Concerts, conferences, and private events'],
        ];

        DB::table('base_commercial_categories')->insert($categories);

        $categoryIds = DB::table('base_commercial_categories')->pluck('id', 'slug');
        $venueCosts = [
            'shop' => [250000, 400000, 625000],
            'bar' => [350000, 560000, 875000],
            'cafe' => [300000, 480000, 750000],
            'food_kiosk' => [150000, 240000, 375000],
            'restaurant' => [500000, 800000, 1250000],
            'casino' => [2500000, 4000000, 6250000],
            'hotel' => [4000000, 6400000, 10000000],
            'vip_hospitality' => [1500000, 2400000, 3750000],
            'fine_dining' => [1000000, 1600000, 2500000],
            'events_venue' => [1200000, 1920000, 3000000],
        ];

        foreach ($venueCosts as $categorySlug => $costs) {
            foreach ($costs as $size => $baseCost) {
                DB::table('base_commercial_venue_costs')->insert([
                    'category_id' => $categoryIds[$categorySlug],
                    'size' => $size + 1,
                    'base_cost' => $baseCost,
                ]);
            }
        }

        $availableStadiumTypes = [
            'shop' => ['local', 'regional', 'global'],
            'bar' => ['village', 'local', 'regional', 'global'],
            'cafe' => ['village', 'local', 'regional', 'global'],
            'food_kiosk' => ['village', 'local'],
            'restaurant' => ['local', 'regional', 'global'],
            'casino' => ['global'],
            'hotel' => ['global'],
            'vip_hospitality' => ['global'],
            'fine_dining' => ['regional', 'global'],
            'events_venue' => ['global'],
        ];
        $availability = [];

        foreach ($availableStadiumTypes as $categorySlug => $stadiumTypes) {
            foreach ($stadiumTypes as $stadiumType) {
                $availability[] = [
                    'category_id' => $categoryIds[$categorySlug],
                    'stadium_type' => $stadiumType,
                ];
            }
        }

        DB::table('base_commercial_category_stadium_type')->insert($availability);

        $products = [
            ['slug' => 'draft-lager', 'category' => 'bar', 'name' => 'Draft Lager', 'description' => 'Cold one, on tap', 'base_price' => 450],
            ['slug' => 'craft-ipa', 'category' => 'bar', 'name' => 'Craft IPA', 'description' => 'Hoppy and bitter', 'base_price' => 550],
            ['slug' => 'house-wine', 'category' => 'bar', 'name' => 'House Wine', 'description' => 'Red or white', 'base_price' => 600],
            ['slug' => 'whiskey-shot', 'category' => 'bar', 'name' => 'Whiskey Shot', 'description' => 'Neat or on the rocks', 'base_price' => 700],
            ['slug' => 'cocktail', 'category' => 'bar', 'name' => 'Manager\'s Cocktail', 'description' => 'House special mix', 'base_price' => 850],
            ['slug' => 'soda', 'category' => 'bar', 'name' => 'Soft Drink', 'description' => 'Cola, lemonade or soda water', 'base_price' => 250],
            ['slug' => 'vinyl-record', 'category' => 'shop', 'name' => 'Vinyl Record', 'description' => 'Classic match anthems, from Vinyl Records', 'base_price' => 1500],
            ['slug' => 'club-scarf', 'category' => 'shop', 'name' => 'Club Scarf', 'description' => 'Official matchday scarf, from Sports Gear', 'base_price' => 1200],
            ['slug' => 'football-boots', 'category' => 'shop', 'name' => 'Football Boots', 'description' => 'Pro-level boots, from Sports Gear', 'base_price' => 4500],
            ['slug' => 'fruit-box', 'category' => 'shop', 'name' => 'Fresh Fruit Box', 'description' => 'Mixed seasonal fruit, from Green Grocer', 'base_price' => 650],
            ['slug' => 'haircut', 'category' => 'shop', 'name' => 'Matchday Haircut', 'description' => 'Fresh trim, from City Cuts Barbershop', 'base_price' => 1800],
            ['slug' => 'programme', 'category' => 'shop', 'name' => 'Matchday Programme', 'description' => 'Official programme, from Books', 'base_price' => 500],
            ['slug' => 'margherita-pizza', 'category' => 'restaurant', 'name' => 'Margherita Pizza', 'description' => 'Mario\'s classic, tomato and mozzarella', 'base_price' => 900],
            ['slug' => 'pepperoni-pizza', 'category' => 'restaurant', 'name' => 'Pepperoni Pizza', 'description' => 'Loaded with spicy pepperoni', 'base_price' => 1050],
            ['slug' => 'sweet-sour-chicken', 'category' => 'restaurant', 'name' => 'Sweet & Sour Chicken', 'description' => 'King Wok house special', 'base_price' => 850],
            ['slug' => 'kung-pao-beef', 'category' => 'restaurant', 'name' => 'Kung Pao Beef', 'description' => 'Wok-fried with peanuts and chilli', 'base_price' => 950],
            ['slug' => 'pad-thai', 'category' => 'restaurant', 'name' => 'Pad Thai', 'description' => 'Good Thai noodles with prawns', 'base_price' => 800],
            ['slug' => 'green-curry', 'category' => 'restaurant', 'name' => 'Green Curry', 'description' => 'Good Thai coconut curry, medium spice', 'base_price' => 900],
        ];

        DB::table('base_commercial_products')->insert(array_map(
            function (array $product) use ($categoryIds): array {
                $product['category_id'] = $categoryIds[$product['category']];
                unset($product['category']);

                return $product;
            },
            $products
        ));
    }
}
