<?php

namespace App\Services\TrainingService\Data;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Services\TrainingService\TrainingIntensity;

readonly class TrainingScheduleData
{
    public function __construct(
        public TrainingCategory $category,
        public TrainingIntensity $intensity,
    ) {}
}
