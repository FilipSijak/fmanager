<?php

namespace AppBundle\GameEngine\GameData\InitialSeed\MiscellaneousData;

class Countries
{
    public static function countries() {
        return [
            [
                'name' => 'England',
                'ranking' => 9200, //this will determine player prices, league quality/number of fans, prizes and comercial deals
                'quality' => 8800, // youth setups/coeficients for generating players, coach quality
                'population' => 66, // millions, stadium capacity, stadium visitors, stadium expansion plans  etc.
            ],
            [
                'name' => 'Spain',
                'ranking' => 8800,
                'quality' => 9600,
                'population' => 46,
            ],
        ];
    }
}