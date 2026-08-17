<?php

namespace App\Services\PersonService\GeneratePeople;

use LogicException;

class PersonDetailsGenerator
{
    public function generate(string $personType): PersonInfo
    {
        throw new LogicException('Person detail generation has not been implemented.');
    }
}
