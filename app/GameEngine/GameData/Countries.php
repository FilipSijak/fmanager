<?php

namespace App\GameEngine\GameData;

class Countries
{
    public static function countries() {
        return [
            [
                'name' => 'England',
                'ranking' => 9200, //this will determine player prices, league quality/number of fans, prizes and comercial deals
                'quality' => 9200, // youth setups/coeficients for generating players, coach quality
                'population' => 66, // millions, stadium capacity, stadium visitors, stadium expansion plans  etc.
            ],
            [
                'name' => 'Spain',
                'ranking' => 8800,
                'quality' => 9400,
                'population' => 46,
            ],
            [
                'name' => 'Italy',
                'ranking' => 8400,
                'quality' => 9600,
                'population' => 60,
            ],
            [
                'name' => 'France',
                'ranking' => 8400,
                'quality' => 7600,
                'population' => 67,
            ],
            [
                'name' => 'Germany',
                'ranking' => 8400,
                'quality' => 8500,
                'population' => 82,
            ],
        ];
    }
}
