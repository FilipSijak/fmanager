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
                'ranking' => 8200,
                'quality' => 7600,
                'population' => 60,
            ],
            [
                'name' => 'France',
                'ranking' => 7400,
                'quality' => 8800,
                'population' => 67,
            ],
            [
                'name' => 'Germany',
                'ranking' => 9300,
                'quality' => 9500,
                'population' => 82,
            ],
        ];
    }
}
