<?php

namespace App\Services\PlayerService\Domain;

use App\Domain\PlayerDevelopment\PlayerAttributeCeiling;
use App\Domain\PlayerDevelopment\PlayerDevelopmentCategory;
use App\Models\Player;
use App\Services\PersonService\Data\PotentialByCategoryData;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PlayerAgeing
{
    public function __construct(
        private readonly PlayerPotential $playerPotential,
        private readonly PlayerAttributeCeiling $playerAttributeCeiling,
    ) {}

    /** @return array<string, int|float> */
    public function attributesOn(Player $player, CarbonInterface $asOfDate): array
    {
        $dateOfBirth = CarbonImmutable::parse($player->person->dob);
        $currentCategoryPotentials = $this->playerPotential->potentialByCategoryOnDate(
            new PotentialByCategoryData(
                technical: (int) $player->technical,
                mental: (int) $player->mental,
                physical: (int) $player->physical,
            ),
            $dateOfBirth,
            $asOfDate
        );
        $attributes = [
            'potential' => $this->playerPotential->onDate((int) $player->max_potential, $dateOfBirth, $asOfDate),
            'current_technical_potential' => $currentCategoryPotentials->technical,
            'current_mental_potential' => $currentCategoryPotentials->mental,
            'current_physical_potential' => $currentCategoryPotentials->physical,
        ];

        foreach (PlayerDevelopmentCategory::cases() as $category) {
            $categoryCeiling = $this->playerAttributeCeiling->forPotential($currentCategoryPotentials->{$category->value});

            foreach ($category->fields() as $field) {
                $attributes[$field] = min((int) $player->{$field}, $categoryCeiling);
            }
        }

        if ($player->position === 'GK') {
            $goalkeeperCeiling = $this->playerAttributeCeiling->forPotential($currentCategoryPotentials->technical);

            foreach (PlayerFields::GOALKEEPING_FIELDS as $field) {
                $attributes[$field] = min((int) $player->{$field}, $goalkeeperCeiling);
            }
        }

        return $attributes;
    }
}
