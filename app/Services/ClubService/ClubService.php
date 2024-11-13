<?php

namespace App\Services\ClubService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Repositories\ClubRepository;
use App\Services\ClubService\FinancialAnalysis\ClubFinancialAnalysis;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\SearchService\SearchService;
use Illuminate\Support\Collection;

class ClubService
{
    private SquadAnalysis         $squadAnalysis;
    private ClubFinancialAnalysis $financialAnalysis;
    private SearchService         $searchService;
    private ClubRepository        $clubRepository;

    public function __construct(
        SquadAnalysis $squadAnalysis,
        ClubFinancialAnalysis $financialAnalysis,
        SearchService $searchService,
        ClubRepository $clubRepository
    )
    {
        $this->squadAnalysis = $squadAnalysis;
        $this->financialAnalysis = $financialAnalysis;
        $this->searchService = $searchService;
        $this->clubRepository = $clubRepository;
    }

    public function clubSellingDecision(Transfer $transfer): bool
    {
        $player = Player::find($transfer->player_id);
        $club = Club::find($transfer->target_club_id);

        if (!$this->squadAnalysis->isAcceptableTransfer($club, $player)) {
            return false;
        }

        if (!$this->financialAnalysis->isFinanciallyAcceptableTransfer($transfer)) {
            // counteroffer?
            return false;
        }

        return true;
    }

    public function playerDeficitByPosition(Club $club): bool|Collection
    {
        if (empty($this->squadAnalysis->optimalNumbersCheckByPosition($club))) {
            return false;
        }

        return Collect($this->squadAnalysis->optimalNumbersCheckByPosition($club));
    }

    public function playerForDeficitPosition1(Club $club)
    {
        if (empty($this->squadAnalysis->optimalNumbersCheckByPosition($club))) {
            return false;
        }

        // check which position is needed
        $positionsShortage = $this->squadAnalysis->optimalNumbersCheckByPosition($club);
        $clubBudget = Account::where('club_id', $club->id)->first();

        foreach ($positionsShortage as $position => $deficitNumbers) {
            // make a search for players of this position
            $playerAttributes = array_merge(
                PlayerPositionConfig::POSITION_PHYSICAL_ATTRIBUTES[$position]['primary'],
                PlayerPositionConfig::POSITION_PHYSICAL_ATTRIBUTES[$position]['secondary'],
                PlayerPositionConfig::POSITION_MENTAL_ATTRIBUTES[$position]['primary'],
                PlayerPositionConfig::POSITION_MENTAL_ATTRIBUTES[$position]['secondary'],
                PlayerPositionConfig::POSITION_TECH_ATTRIBUTES[$position]['primary'],
                PlayerPositionConfig::POSITION_TECH_ATTRIBUTES[$position]['secondary'],
            );

            $currentAveragePlayerAttributesByPosition = $this->clubRepository->getAverageAttributesByPosition(
                $club->id, $position, $playerAttributes
            );

            // filter players who are too expensive
            // remove players that were offered before (check Transfers table for the past 2 years)
            $foundPlayers = $this->searchService->transferSearchForPlayerByAttributes(
                $club,
                $currentAveragePlayerAttributesByPosition
            );

            $foundPlayers = $foundPlayers->filter(function ($player) use($clubBudget, $club) {
                return $player->value < $clubBudget->transfer_budget &&
                       $player->potential >= $club->rank * 10 - 20;
            });

            // pick the best player from the rest
            return $foundPlayers->where('potential', $foundPlayers->max('potential'))->first();
        }
    }


    public function transferHandler()
    {

    }

    public function loanHandler()
    {

    }
}
