<?php

namespace App\Services\TacticsService;

enum Mentality: string
{
    case DEFENSIVE = 'defensive';
    case BALANCED = 'balanced';
    case ATTACKING = 'attacking';
}
