<?php

namespace App\Services\TrainingService;

enum TrainingIntensity: int
{
    case None = 0;
    case Light = 1;
    case Medium = 2;
    case Hard = 3;
}
