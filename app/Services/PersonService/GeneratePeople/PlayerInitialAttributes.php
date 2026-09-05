<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Random\Randomizer;

class PlayerInitialAttributes
{
    const PRIMARY_ATTRIBUTES = 'primary_attributes';

    const SECONDARY_ATTRIBUTES = 'secondary_attributes';

    const OTHER_ATTRIBTUES = 'other_attributes';

    protected string $playerPosition;

    protected array $playerAllAttributes = [];

    protected array $commonAttributes = ['stamina', 'acceleration', 'strength'];

    protected array $playerPotentialByCategory;

    public function __construct(private readonly Randomizer $randomizer = new Randomizer) {}

    public function setPlayerPosition(string $playerPosition): self
    {
        $this->playerPosition = $playerPosition;

        return $this;
    }

    public function setPlayerPotentialByCategory(array $playerPotentialByCategory): self
    {
        $this->playerPotentialByCategory = $playerPotentialByCategory;

        return $this;
    }

    public function initAllAttributes(): array
    {
        $this->playerAllAttributes = [];
        $mainAttributes = PlayerPositionConfig::getPositionMainAttributes($this->playerPosition);

        foreach ($mainAttributes as $attributesCategory => $importanceList) {
            $this->setPrimaryAttributes($importanceList['primary'], $attributesCategory);
            $this->setSecondaryAttributes($importanceList['secondary'], $attributesCategory);
        }

        $this->setOtherAttributes();

        return $this->playerAllAttributes;
    }

    /**
     * This will set attributes for a specific category (e.g. technical)
     * Goes through each primary attribute and gives it a random value from a higher range
     */
    protected function setPrimaryAttributes(array $primaryAttributes, string $attributesCategory): void
    {
        $potentialByCategory = $this->playerPotentialByCategory[$attributesCategory];
        $reducedPotential = $this->potentialReduction($potentialByCategory, self::PRIMARY_ATTRIBUTES);

        foreach ($primaryAttributes as $attribute) {
            $this->playerAllAttributes[$attribute] = (int) round(
                $this->randomizer->getInt($potentialByCategory - $reducedPotential, $potentialByCategory) / 10
            );
        }
    }

    private function potentialReduction(int $playerPotential, string $type): int
    {
        $potentialDescription = PersonPotential::personPotentialLabel($playerPotential);

        switch ($type) {
            case self::PRIMARY_ATTRIBUTES:
                $potentialReductionByLabel = [
                    'amateur' => 2,
                    'low' => 4,
                    'professional' => 5,
                    'normal' => 6,
                    'high' => 7,
                    'very_high' => 10,
                    'world_class' => 15,
                ];
                break;
            case self::SECONDARY_ATTRIBUTES:
                $potentialReductionByLabel = [
                    'amateur' => 4,
                    'low' => 6,
                    'professional' => 12,
                    'normal' => 18,
                    'high' => 25,
                    'very_high' => 30,
                    'world_class' => 40,
                ];
                break;
            case self::OTHER_ATTRIBTUES:
                $potentialReductionByLabel = [
                    'amateur' => 10,
                    'low' => 15,
                    'professional' => 20,
                    'normal' => 30,
                    'high' => 40,
                    'very_high' => 50,
                    'world_class' => 60,
                ];
                break;
            default:
                $potentialReductionByLabel = [
                    'amateur' => 10,
                    'low' => 15,
                    'professional' => 20,
                    'normal' => 25,
                    'high' => 30,
                    'very_high' => 40,
                    'world_class' => 45,
                ];
        }

        return $potentialReductionByLabel[$potentialDescription];
    }

    /**
     * Secondary attributes would be lower than main (on average) and higher than the rest
     * If a striker has finishing attribute of 20, his secondary attribute for that position (dribbling) will be lower
     * (15) but still higher than tackling (8)
     */
    protected function setSecondaryAttributes(array $attributes, string $attributesCategory): void
    {
        $potentialByCategory = $this->playerPotentialByCategory[$attributesCategory];
        $reducedPotential = $this->potentialReduction($potentialByCategory, self::SECONDARY_ATTRIBUTES);

        foreach ($attributes as $attribute) {
            $this->playerAllAttributes[$attribute] = (int) round(
                $this->randomizer->getInt($potentialByCategory - $reducedPotential, $potentialByCategory) / 10
            );
        }
    }

    /**
     * Sets the rest of the player attributes that weren't filled by primary or secondary run
     */
    protected function setOtherAttributes(): void
    {
        $fieldsByCategory = [
            'technical' => PlayerFields::TECHNICAL_FIELDS,
            'mental' => PlayerFields::MENTAL_FIELDS,
            'physical' => PlayerFields::PHYSICAL_FIELDS,
        ];

        foreach ($fieldsByCategory as $category => $fields) {
            foreach ($fields as $field) {
                // checks the object if the attribute was already set for primary or secondary value
                if (! isset($this->playerAllAttributes[$field])) {
                    $potentialForCategory = $this->playerPotentialByCategory[$category];
                    $reducedPotential = $this->potentialReduction($potentialForCategory, self::OTHER_ATTRIBTUES);
                    $minimumAttributeValue = $this->setMinimumAttributeValue($potentialForCategory);

                    if (in_array($field, $this->commonAttributes, true)) {
                        $minimumAttributeValue = $minimumAttributeValue + 3;
                    }

                    /*
                     * After getting a minimal value for an attribute, the value is used as a starting point for rand
                     * His potential will be reduced so non important attributes don't get too high
                     * Example: potential = 150  rand (7, (150 - 50) / 10) or rand between 7 and 10
                     * Players with lower potential will get their potential reduced less
                     */
                    $this->playerAllAttributes[$field] = (int) round(
                        $this->randomizer->getInt(
                            $minimumAttributeValue,
                            max($minimumAttributeValue, (int) (($potentialForCategory - $reducedPotential) / 10)),
                        )
                    );
                }
            }
        }
    }

    private function setMinimumAttributeValue(int $playerPotential): int
    {
        $potentialDescription = PersonPotential::personPotentialLabel($playerPotential);

        $potentialMinimumAttributesRanges = [
            'amateur' => 3,
            'low' => 4,
            'professional' => 5,
            'normal' => 6,
            'high' => 7,
            'very_high' => 8,
            'world_class' => 9,
        ];

        return $potentialMinimumAttributesRanges[$potentialDescription];
    }
}
