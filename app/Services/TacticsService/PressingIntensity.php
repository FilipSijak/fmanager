<?php

namespace App\Services\TacticsService;

enum PressingIntensity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
