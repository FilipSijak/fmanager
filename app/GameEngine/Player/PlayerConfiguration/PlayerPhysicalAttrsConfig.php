<?php

namespace App\GameEngine\Player\PlayerConfiguration;

class PlayerPhysicalAttrsConfig
{
    const PHYSICAL_ATTRS_BY_TYPE = [
        'quick' => ['pace', 'agility', 'balance'],
        'strong' => ['strength', 'naturalFitness'],
        'endurable' => ['stamina', 'naturalFitness'],
        'fast' => ['acceleration', 'balance', 'pace', 'naturalFitness'],
    ];

    public static function getPhysicalTypeBasedOnPosition($position)
    {
        $typesByPosition = [
            'forward' => ['quick', 'strong', 'fast'],
            'defending_middfielder' => ['endurable', 'strong'],
            'creative_middfielder' => ['quick'],
            'center_back' => ['strong'],
            'wing_back' => ['quick', 'endurable', 'fast'],
            'winger' => ['quick', 'fast']
        ];
        $specifiedPositionTypes = $typesByPosition[$position];
        $type = $specifiedPositionTypes[rand(0,count($specifiedPositionTypes) -1)];

        return $type;
    }
}
