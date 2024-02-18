<?php

namespace App\Services\PersonService\PersonConfig\Player;

class PlayerPositionConfig
{
    const PLAYER_POSITIONS = [
        1 => 'CB',
        2 => 'LB',
        3 => 'LWB',
        4 => 'RB',
        5 => 'RWB',
        6 => 'DMC',
        7 => 'CM',
        8 => 'LW',
        9 => 'LF',
        10 => 'AMC',
        11 => 'RW',
        12 => 'RF',
        13 => 'CF',
        14 => 'ST'
    ];

    const POSITION_TECH_ATTRIBUTES = [
        'CB'  => [
            'primary'   => ['marking', 'tackling'],
            'secondary' => ['heading'],
        ],
        'LB'  => [
            'primary'   => ['crossing', 'tackling'],
            'secondary' => ['long_throws', 'marking'],
        ],
        'LWB' => [
            'primary'   => ['crossing', 'passing'],
            'secondary' => ['first_touch', 'tackling'],
        ],
        'RB'  => [
            'primary'   => ['crossing', 'tackling'],
            'secondary' => ['long_throws', 'marking'],
        ],
        'RWB' => [
            'primary'   => ['crossing', 'passing'],
            'secondary' => ['first_touch', 'tackling'],
        ],
        'DMC' => [
            'primary'   => ['tackling', 'passing'],
            'secondary' => ['marking', 'heading'],
        ],
        'CM'  => [
            'primary'   => ['passing', 'first_touch'],
            'secondary' => ['technique'],
        ],
        'AMC' => [
            'primary'   => ['passing', 'first_touch'],
            'secondary' => ['finishing', 'dribbling'],
        ],
        'LW'  => [
            'primary'   => ['crossing', 'dribbling'],
            'secondary' => ['passing', 'first_touch'],
        ],
        'LF'  => [
            'primary'   => ['finishing', 'dribbling'],
            'secondary' => ['passing', 'first_touch'],
        ],
        'RW'  => [
            'primary'   => ['crossing', 'dribbling'],
            'secondary' => ['passing', 'first_touch'],
        ],
        'RF'  => [
            'primary'   => ['finishing', 'dribbling'],
            'secondary' => ['passing', 'first_touch'],
        ],
        'CF'  => [
            'primary'   => ['finishing', 'first_touch'],
            'secondary' => ['dribbling', 'technique'],
        ],
        'ST'  => [
            'primary'   => ['finishing',  'first_touch'],
            'secondary' => ['heading', 'dribbling'],
        ],
    ];

    const POSITION_MENTAL_ATTRIBUTES = [
        'CB'  => [
            'primary'   => ['positioning', 'determination'],
            'secondary' => ['concentration', 'bravery'],
        ],
        'LB'  => [
            'primary'   => ['positioning', 'workrate'],
            'secondary' => ['decisions', 'concentration'],
        ],
        'LWB' => [
            'primary'   => ['positioning', 'of_the_ball'],
            'secondary' => ['workrate'],
        ],
        'RB'  => [
            'primary'   => ['positioning', 'workrate'],
            'secondary' => ['decisions', 'concentration'],
        ],
        'RWB' => [
            'primary'   => ['positioning', 'of_the_ball'],
            'secondary' => ['workrate'],
        ],
        'DMC' => [
            'primary'   => ['positioning', 'workrate', 'determination'],
            'secondary' => ['teamwork', 'leadership'],
        ],
        'CM'  => [
            'primary'   => ['creativity', 'of_the_ball'],
            'secondary' => ['teamwork', 'teamwork'],
        ],
        'AMC' => [
            'primary'   => ['creativity', 'flair'],
            'secondary' => ['of_the_ball'],
        ],
        'LW'  => [
            'primary'   => ['of_the_ball'],
            'secondary' => ['anticipation'],
        ],
        'LF'  => [
            'primary'   => ['of_the_ball', 'flair'],
            'secondary' => ['composure'],
        ],
        'RW'  => [
            'primary'   => ['of_the_ball'],
            'secondary' => ['anticipation'],
        ],
        'RF'  => [
            'primary'   => ['of_the_ball', 'flair'],
            'secondary' => ['composure'],
        ],
        'CF'  => [
            'primary'   => ['of_the_ball', 'flair'],
            'secondary' => ['composure', 'anticipation'],
        ],
        'ST'  => [
            'primary'   => ['composure', 'anticipation'],
            'secondary' => ['concentration'],
        ],
    ];

    const POSITION_PHYSICAL_ATTRIBUTES = [
        'CB'  => [
            'primary'   => ['strength'],
            'secondary' => ['jumping'],
        ],
        'LB'  => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['stamina'],
        ],
        'LWB' => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['stamina'],
        ],
        'RB'  => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['stamina'],
        ],
        'RWB' => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['stamina'],
        ],
        'DMC' => [
            'primary'   => ['stamina', 'strength'],
            'secondary' => ['natural_fitness'],
        ],
        'CM'  => [
            'primary'   => ['stamina', 'agility'],
            'secondary' => ['natural_fitness'],
        ],
        'AMC' => [
            'primary'   => ['agility'],
            'secondary' => ['balance'],
        ],
        'LW'  => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['agility'],
        ],
        'LF'  => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['agility', 'balance'],
        ],
        'RW'  => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['agility'],
        ],
        'RF'  => [
            'primary'   => ['pace', 'acceleration'],
            'secondary' => ['agility', 'balance'],
        ],
        'CF'  => [
            'primary'   => ['pace', 'agility', 'acceleration'],
            'secondary' => [],
        ],
        'ST'  => [
            'primary'   => ['balance', 'agility'],
            'secondary' => ['jumping'],
        ],
    ];

    // return array of primary and secondary attributes
    public static function getPositionMainAttributes($position)
    {
        return [
            'technical' => self::POSITION_TECH_ATTRIBUTES[$position],
            'mental'    => self::POSITION_MENTAL_ATTRIBUTES[$position],
            'physical'  => self::POSITION_PHYSICAL_ATTRIBUTES[$position],
        ];
    }
}
