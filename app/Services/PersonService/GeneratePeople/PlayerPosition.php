<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;

class PlayerPosition
{
    public function getInitialPositionsBasedOnAttributes($attributesValues): array
    {
        $positionList = PlayerPositionConfig::PLAYER_POSITIONS;
        $positionListMainAttributes = [];

        foreach ($positionList as $position) {
            if (!isset($positionListMainAttributes[$position]['primary'])) {
                $positionListMainAttributes[$position]['primary'] = [];
            }

            if (!isset($positionListMainAttributes[$position]['secondary'])) {
                $positionListMainAttributes[$position]['secondary'] = [];
            }

            $positionListMainAttributes[$position]['primary'][] = array_merge(
                PlayerPositionConfig::POSITION_TECH_ATTRIBUTES[$position]['primary'],
                PlayerPositionConfig::POSITION_MENTAL_ATTRIBUTES[$position]['primary'],
                PlayerPositionConfig::POSITION_PHYSICAL_ATTRIBUTES[$position]['primary'],
            );

            $positionListMainAttributes[$position]['secondary'][] = array_merge(
                PlayerPositionConfig::POSITION_TECH_ATTRIBUTES[$position]['secondary'],
                PlayerPositionConfig::POSITION_MENTAL_ATTRIBUTES[$position]['secondary'],
                PlayerPositionConfig::POSITION_PHYSICAL_ATTRIBUTES[$position]['secondary']
            );
        }

        return $this->getAverageGradeByPosition($positionListMainAttributes, $attributesValues);
    }

    /**
     * @param array $positionsWithMainAttributes
     * @param array $playerAttributeValues
     *
     * @return array
     */
    private function getAverageGradeByPosition(array $positionsWithMainAttributes, array $playerAttributeValues): array
    {
        $averageGradeForPosition = [];

        foreach ($positionsWithMainAttributes as $position => $positionAttributes) {
            $averageGradeForPosition[$position] = 0;
            $count                              = 0;

            if (isset($positionAttributes['primary'][0])) {
                foreach ($positionAttributes['primary'][0] as $attribute) {
                    $count++;

                    if (!isset($playerAttributeValues[$attribute])) {
                        echo $attribute;
                    } else {
                        $averageGradeForPosition[$position] += $playerAttributeValues[$attribute] + 8;
                    }

                }
            }

            if (isset($positionAttributes['secondary'][0])) {
                foreach ($positionAttributes['secondary'][0] as $attribute) {
                    $count++;

                    if (!isset($playerAttributeValues[$attribute])) {
                        echo $attribute;
                    } else {
                        $averageGradeForPosition[$position] += $playerAttributeValues[$attribute] + 5;
                    }

                }
            }

            $averageGradeForPosition[$position] = $averageGradeForPosition[$position] / $count;
        }

        arsort($averageGradeForPosition);

        return array_slice($averageGradeForPosition, 0,3);
    }
}
