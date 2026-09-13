<?php

namespace Tests\Integration\Stadium;

use App\Services\StadiumService\StadiumType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumTypeTest extends TestCase
{
    #[Test]
    public function it_maps_capacity_to_a_stadium_type(): void
    {
        $this->assertSame(StadiumType::VILLAGE, StadiumType::fromCapacity(20000));
        $this->assertSame(StadiumType::LOCAL, StadiumType::fromCapacity(20001));
        $this->assertSame(StadiumType::REGIONAL, StadiumType::fromCapacity(40001));
        $this->assertSame(StadiumType::GLOBAL, StadiumType::fromCapacity(70001));
    }

    #[Test]
    public function it_defines_capacity_and_commercial_limits_for_each_type(): void
    {
        $this->assertSame(20000, StadiumType::VILLAGE->maximumCapacity());
        $this->assertSame(1, StadiumType::VILLAGE->commercialLimit());
        $this->assertSame(40000, StadiumType::LOCAL->maximumCapacity());
        $this->assertSame(3, StadiumType::LOCAL->commercialLimit());
        $this->assertSame(70000, StadiumType::REGIONAL->maximumCapacity());
        $this->assertSame(6, StadiumType::REGIONAL->commercialLimit());
        $this->assertSame(100000, StadiumType::GLOBAL->maximumCapacity());
        $this->assertSame(10, StadiumType::GLOBAL->commercialLimit());
    }
}
