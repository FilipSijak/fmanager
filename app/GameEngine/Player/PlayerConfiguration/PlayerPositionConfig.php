<?php

namespace App\GameEngine\Player\PlayerConfiguration;

class PlayerPositionConfig
{
    const POSITION_TECH_ATTRIBUTES = [
        'forward' => [
            'primary' => ['finishing', 'dribbling', 'first_touch'],
            'secondary' => ['passing']
        ],
        'defending_middfielder' => [
            'primary' => ['tackling'],
            'secondary' => ['passing']
        ],
        'creative_middfielder' => [
            'primary' => ['passing', 'first_touch'],
            'secondary' => ['finishing', 'dribbling']
        ],
        'center_back' => [
            'primary' => ['heading', 'tackling'],
            'secondary' => []
        ],
        'winger' => [
            'primary' => ['crossing', 'dribbling', 'first_touch'],
            'secondary' => ['passing']
        ],
        'wing_back' => [
            'primary' => ['crossing'],
            'secondary' => ['passing', 'first_touch', 'dribbling']
        ],
    ];

    const POSITION_MENTAL_ATTRIBUTES = [
        'forward' => [
            'primary' => ['of_the_ball', 'flair', 'composure'],
            'secondary' => []
        ],
        'defending_middfielder' => [
            'primary' => ['positioning', 'workrate', 'determination'],
            'secondary' => ['teamwork', 'positioning']
        ],
        'creative_middfielder' => [
            'primary' => ['creativity', 'flair'],
            'secondary' => ['of_the_ball', 'composure']
        ],
        'center_back' => [
            'primary' => ['positioning', 'determination'],
            'secondary' => ['concentration']
        ],
        'wing_back' => [
            'primary' => ['positioning', 'workrate'],
            'secondary' => ['of_the_ball']
        ],
        'winger' => [
            'primary' => ['flair', 'of_the_ball'],
            'secondary' => ['workrate']
        ],
    ];

    const POSITION_PHYSICAL_ATTRIBUTES = [
        'forward' => [
            'primary' => ['pace', 'acceleration'],
            'secondary' => []
        ],
        'defending_middfielder' => [
            'primary' => ['stamina'],
            'secondary' => ['strength']
        ],
        'creative_middfielder' => [
            'primary' => ['agility'],
            'secondary' => []
        ],
        'winger' => [
            'primary' => ['pace', 'stamina'],
            'secondary' => ['agility', 'acceleration']
        ],
        'wing_back' => [
            'primary' => ['pace', 'acceleration'],
            'secondary' => ['stamina']
        ],
        'center_back' => [
            'primary' => ['strength'],
            'secondary' => ['jumping']
        ],
    ];

    const PLAYER_POSITIONS = [
        'forward', 'defending_middfielder', 'creative_middfielder', 'center_back', 'wing_back', 
        'winger'
    ];

    public static function getRandomPosition()
    {
        return self::PLAYER_POSITIONS[rand(0, count(self::PLAYER_POSITIONS) -1)];
    }

    // return array of primary and secondary attributes
    public static function getPositionMainAttributes($position)
    {
        return [
            'technical' => self::POSITION_TECH_ATTRIBUTES[$position],
            'mental' => self::POSITION_MENTAL_ATTRIBUTES[$position],
            'physical' => self::POSITION_PHYSICAL_ATTRIBUTES[$position]
        ];
    }
}