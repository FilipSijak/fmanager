<?php

namespace App\Domain\PlayerDevelopment;

enum TrainingCategory: int
{
    case Physical = 1;
    case Tactical = 2;
    case Technical = 3;
    case Goalkeeping = 4;
}
