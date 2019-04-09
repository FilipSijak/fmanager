<?php

namespace AppBundle\GameEngine\GameData\ClubsByCountry;

class ClubsGermany
{
    public static function clubs
    {
        return [
            [
                'name' => 'FC Augsburg',
                'league_id' => 3,
                'city' => 1,
                'country' => 5,
                'coefficient' => 93, // uefa competitions coefficient
                'coeff1' => 19, // uefa coefficient for the last year to last five years
                'coeff2' => 15,
                'coeff3' => 26,
                'coeff4' => 18,
                'coeff5' => 18,
                'world_ranking' => 8600, // decides popularity of the club, range from 1-10 000, it gives more commercial inc, players wants to come and play here
                'stadium_id' => 1,
                'account_balance' => 180 //in milions of euros
            ],
            
        ];
    }
}