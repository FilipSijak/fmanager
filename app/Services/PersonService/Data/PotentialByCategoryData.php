<?php

namespace App\Services\PersonService\Data;

readonly class PotentialByCategoryData
{
    public function __construct(
        public int $technical,
        public int $mental,
        public int $physical,
    ) {}
}
