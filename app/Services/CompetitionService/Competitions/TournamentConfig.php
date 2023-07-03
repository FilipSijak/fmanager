<?php

namespace App\Services\CompetitionService\Competitions;

use Carbon\Carbon;

class TournamentConfig
{
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
