<?php

namespace App\GameEngine\Competition;

abstract class AbstractGenerateCompetition
{
    public function printArray($array)
    {
        echo "<pre>";
        print_r($array);
        echo "</pre>";
    }
}