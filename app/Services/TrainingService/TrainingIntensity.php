<?php

namespace App\Services\TrainingService;

enum TrainingIntensity: int
{
    case Light = 1;
    case Medium = 2;
    case Hard = 3;
    case None = 4;
}
