<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\Data\PotentialByCategoryData;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Random\Randomizer;

class PersonPotential
{
    const POTENTIAL_BOUNDARIES = [0, 50, 75, 100, 130, 160, 180, 200];

    protected readonly Randomizer $randomizer;

    public function __construct(?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new Randomizer;
    }

    public function calculatePotentialByCategory(int $potential): PotentialByCategoryData
    {
        $personPotential = [];
        $potentialValue = 0;
        $offset = count(PlayerFields::PERSON_ATTRIBUTE_CATEGORIES);

        for ($i = 0; $i < $offset; $i++) {
            if (in_array($potential, self::POTENTIAL_BOUNDARIES)) {
                $personPotential[PlayerFields::PERSON_ATTRIBUTE_CATEGORIES[$i]] = $potential;

                continue;
            }

            for ($k = 1; $k < count(self::POTENTIAL_BOUNDARIES); $k++) {
                if ($potential < self::POTENTIAL_BOUNDARIES[$k] && $potential > self::POTENTIAL_BOUNDARIES[$k - 1]) {
                    $potentialValue = $this->randomizer->getInt(
                        self::POTENTIAL_BOUNDARIES[$k - 1],
                        self::POTENTIAL_BOUNDARIES[$k],
                    );
                }
            }

            $personPotential[PlayerFields::PERSON_ATTRIBUTE_CATEGORIES[$i]] = $potentialValue;
        }

        return new PotentialByCategoryData(
            technical: $personPotential['technical'],
            mental: $personPotential['mental'],
            physical: $personPotential['physical'],
        );
    }

    public static function personPotentialLabel(int $potential): string
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

        foreach ($labels as $label => $labelCoefficient) {
            if ($potential <= $labelCoefficient) {
                return $label;
            }
        }

        return array_key_last($labels);
    }
}
