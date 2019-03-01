<?php

namespace App\GameEngine\Player;

class PlayerPotential
{
    const POTENTIAL_BOUNDERIES = [0, 50, 75, 100, 130, 160, 180, 200];

    // Creates random value for tehnical, mental and physical potential
    // returns object with each value
    public static function calculatePlayerPotential($coef)
    {
        $playerPotential = new \stdClass();
        $playerAttributesCategories = ['technical', 'mental', 'physical'];

        for ($i = 0; $i < 3; $i++) {
            for ($k = 1; $k < count(self::POTENTIAL_BOUNDERIES); $k++) {
                if ($coef < self::POTENTIAL_BOUNDERIES[$k] && $coef > self::POTENTIAL_BOUNDERIES[$k - 1]) {
                    $potentialValue = rand(self::POTENTIAL_BOUNDERIES[$k - 1], self::POTENTIAL_BOUNDERIES[$k]);
                }
            }

            $playerPotential->{$playerAttributesCategories[$i]} = $potentialValue;
        }

        return $playerPotential;
    }

    public static function playerPotentialLabel($potential)
    {
        $labels = [
            'amateur' => 50,
            'low' => 75,
            'professional' => 100,
            'normal' => 130,
            'high' => 160,
            'very_high' => 180,
            'world_class' => 200,
        ];

        $labels_flipped = array_flip($labels);

        foreach ($labels as $label_coeficient) {
            if ($potential <= $label_coeficient) {
                return $labels_flipped[$label_coeficient];
            }
        }
    }
}
