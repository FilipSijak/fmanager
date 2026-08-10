<?php

namespace App\Services\CompetitionService\Competitions;

use Carbon\Carbon;
use DateTimeInterface;

class TournamentConfig
{
    private Carbon $startDate;

    private Carbon $winterKnockoutStartDate;

    public function __construct(string|DateTimeInterface|null $seasonStartDate = null)
    {
        $this->startDate = $seasonStartDate === null
            ? Carbon::create((int) date('Y'), 8, 15)
            : Carbon::parse($seasonStartDate);

        $this->winterKnockoutStartDate = Carbon::create(
            $this->startDate->year + 1,
            2,
            15
        );
    }

    public function getStartDate(): Carbon
    {
        return $this->startDate->copy();
    }

    public function getWinterKnockoutStartDate(): Carbon
    {
        return $this->winterKnockoutStartDate->copy();
    }

    public function firstTuesdayOnOrAfter(string|DateTimeInterface $date): Carbon
    {
        $date = Carbon::parse($date)->startOfDay();

        return $date->isTuesday() ? $date : $date->next(Carbon::TUESDAY);
    }

    public function getSecondLegDate(string|DateTimeInterface $firstLegDate): Carbon
    {
        return Carbon::parse($firstLegDate)->addWeek();
    }

    public function getNextRoundStartDate(string|DateTimeInterface $lastFeederDate): Carbon
    {
        return Carbon::parse($lastFeederDate)->addWeek();
    }
}
