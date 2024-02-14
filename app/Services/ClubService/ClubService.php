<?php

namespace App\Services\ClubService;

use App\Models\Club;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;

class ClubService
{
    private SquadAnalysis $squadAnalysis;

    public function __construct(
        SquadAnalysis $squadAnalysis
    )
    {
        $this->squadAnalysis = $squadAnalysis;
    }

    public function internalSquadAnalysis(Club $club)
    {
        $this->squadAnalysis->optimalNumbersCheckByPosition($club);
    }

    public function clubFinancialSummary()
    {

    }

    public function transferHandler()
    {

    }

    public function loanHandler()
    {

    }
}
