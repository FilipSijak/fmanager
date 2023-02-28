<?php

namespace App\Services\CompetitionService\Competitions;

use Illuminate\Support\Carbon;

class CompetitionConfig
{
    /**
     * @var Carbon|\Carbon\CarbonImmutable
     */
    private $startDate;
    /**
     * @var Carbon|\Carbon\CarbonImmutable
     */
    private $winterKnockoutStartDate;

    public function __construct()
    {
        $this->startDate = Carbon::create((int)date("Y"), 8, 15);
        $this->winterKnockoutStartDate = Carbon::create((int)date("Y") + 1, 2, 15);
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function getWinterKnockoutStartDate()
    {
        return $this->winterKnockoutStartDate;
    }
}
