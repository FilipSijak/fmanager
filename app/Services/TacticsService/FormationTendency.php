<?php

namespace App\Services\TacticsService;

enum FormationTendency: string
{
    case DEFENSIVE = 'defensive';
    case BALANCED = 'balanced';
    case ATTACKING = 'attacking';
}
