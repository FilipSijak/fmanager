<?php

namespace App\GameEngine\Player;

use App\GameEngine\Player\PlayerConfiguration\PlayerFieldsConfig;
use App\GameEngine\Player\PlayerConfiguration\PlayerPositionConfig;
use App\GameEngine\Player\PlayerPotential;

class PlayerInitialAttributes
{
    protected $player_potential;
    protected $player_all_attributes = [];
    protected $player_position;
    protected $player_main_attrs;
    protected $commonAttributes = ['stamina', 'acceleration', 'strength'];

    public function __construct($player_potential, $player_position)
    {
        $this->player_potential = $player_potential;
        $this->player_position = $player_position;
    }

    // player physical - i could add attribute in his fitness, it would be rating for physique
    // - it would only be his starting point, fitness attributes would change with his injuries
    // and age, not potential 
    // starting phisique would be identical to overall potential

    // player mental - mental should be separated from  physical and tehniqe, good players could
    // have bad mentality, it would change with overall happiness and age

    // 190 physical - type speed would give him great pace

    // 170 potential = 17 -max skill from his position important tehnique attributes
    // 183 would be 18 max skill
    // 176 would be 18 max skill

    public function getAllAttributeValues()
    {
        $this->setMainAttributes();

        return $this->player_all_attributes;
    }

    protected function setMainAttributes()
    {
        $attributeCategories = ['technical', 'mental', 'physical'];
        $mainAttributes = PlayerPositionConfig::getPositionMainAttributes($this->player_position);

        foreach ($mainAttributes as $attributesCategory => $importanceList) {
            $this->setPrimaryAttributes($importanceList['primary'], $attributesCategory);
            $this->setSecondaryAttributes($importanceList['secondary'], $attributesCategory);
            $this->setOtherAttributes();
        }
    }

    protected function setPrimaryAttributes($attributes, $attributesCategory)
    {
        foreach ($attributes as $attribute) {
            $this->player_all_attributes[$attribute] = (int) round(rand($this->player_potential[$attributesCategory] - 15, $this->player_potential[$attributesCategory]) / 10 );
        }
    }

    // player should have main attributes higher than others, secondary would also be good
    // e.q. strikier can have 18 finishing but secondary attr would be heading or crossing
    // secondary attributes would have range $main_coeff -8 for random
    protected function setSecondaryAttributes($attributes, $attributesCategory)
    {
        foreach ($attributes as $attribute) {
            $this->player_all_attributes[$attribute] = (int) round(rand($this->player_potential[$attributesCategory] - 40, $this->player_potential[$attributesCategory]) / 10 );
        }
    }

    protected function setOtherAttributes()
    {
        $all_tehnical_fields = PlayerFieldsConfig::TEHNICAL_FIELDS;
        $all_mental_fields = PlayerFieldsConfig::MENTAL_FIELDS;
        $all_physical_fields = PlayerFieldsConfig::PHYSICAL_FILDS;

        $attributeCategories = ['technical', 'mental', 'physical'];

        $all_abillity_attributes = array_merge($all_tehnical_fields, $all_mental_fields, $all_physical_fields);

        foreach ($all_abillity_attributes as $field) {
            foreach($attributeCategories as $category) {
                if (!isset($this->player_all_attributes[$field])) {

                    $minimumAttributeValue = self::setMinimumAttributeValue($this->player_potential[$category]);

                    if (isset($this->commonAttributes[$field])) {
                        dump($this->commonAttributes[$field]);
                        $minimumAttributeValue = $minimumAttributeValue + 3;
                    }

                    $this->player_all_attributes[$field] = (int)round(rand(9, $this->player_potential[$category] / 10));
                }
            }
        }
    }

    // This will depend on player potential coeficient
    // Returns minimum value for all attributes, so even if a player is attacker, his starting position would be above 1 for marking
    protected static function setMinimumAttributeValue($coef)
    {
        $potentialDescription = PlayerPotential::playerPotentialLabel($coef);

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