<?php

namespace Tests\Unit\Services\PersonService\GeneratePeople;

use App\Services\PersonService\GeneratePeople\PlayerPosition;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use PHPUnit\Framework\TestCase;

class PlayerPositionTest extends TestCase
{
    public function test_goalkeepers_receive_a_goalkeeper_grade_and_outfield_players_do_not(): void
    {
        $attributes = array_fill_keys(array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS,
        ), 10);
        $goalkeeperAttributes = array_merge($attributes, array_fill_keys(PlayerFields::GOALKEEPING_FIELDS, 15));
        $outfieldAttributes = array_merge($attributes, array_fill_keys(PlayerFields::GOALKEEPING_FIELDS, 1));
        $positions = new PlayerPosition;

        $goalkeeperGrades = $positions->getInitialPositionsBasedOnAttributes($goalkeeperAttributes, 'GK');
        $outfieldGrades = $positions->getInitialPositionsBasedOnAttributes($outfieldAttributes, 'CB');

        $this->assertCount(3, $goalkeeperGrades);
        $this->assertArrayHasKey('GK', $goalkeeperGrades);
        $this->assertCount(3, $outfieldGrades);
        $this->assertArrayNotHasKey('GK', $outfieldGrades);
    }
}
