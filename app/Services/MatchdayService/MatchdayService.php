<?php

namespace App\Services\MatchdayService;

use App\Models\Game;
use App\Models\MatchdaySettlement;
use App\Repositories\MatchdayRepository;
use App\Repositories\StadiumCapacityRepository;
use App\Services\AccountingService\AccountingService;
use App\Services\CommercialService\CommercialDemandService;
use App\Services\CommercialService\CommercialService;
use App\Services\CommercialService\Domain\TicketPriceCalculator;
use App\Services\CommercialService\Domain\TicketType;
use App\Services\CommercialService\SeasonTicketService;
use App\Services\MatchdayService\Domain\MatchdayCalculator;
use Carbon\CarbonImmutable;

class MatchdayService
{
    public function __construct(
        private readonly MatchdayRepository $repository,
        private readonly StadiumCapacityRepository $stadiumCapacity,
        private readonly MatchdayCalculator $calculator,
        private readonly AccountingService $accounting,
        private readonly CommercialService $commercial,
        private readonly TicketPriceCalculator $ticketPriceCalculator,
        private readonly SeasonTicketService $seasonTickets,
        private readonly CommercialDemandService $demand,
    ) {}

    public function settle(Game $game): ?MatchdaySettlement
    {
        return $this->repository->transaction(function () use ($game): ?MatchdaySettlement {
            $game = $this->repository->gameForUpdate($game);
            if ($game->status !== Game::STATUS_COMPLETED || $game->match_start === null) {
                return null;
            }
            $existing = $this->repository->settlement($game);
            if ($existing !== null) {
                return $existing;
            }

            $home = $this->repository->club($game, $game->hometeam_id);
            $away = $this->repository->club($game, $game->awayteam_id);
            $stadium = $this->repository->stadium($game);
            $competition = $this->repository->competition($game);
            $seasonTicketSale = $this->seasonTickets->saleForMatch($home, $game, $competition);
            $seasonTicketHolders = $seasonTicketSale?->quantity ?? 0;
            $stadium = $this->commercial->initializeStadium($stadium);
            $ticketType = TicketType::forCompetition($competition->type, $competition->competition_scope ?? 'domestic');
            $ticketPrice = $this->ticketPriceCalculator->forType((int) $stadium->ticket_price, $ticketType);
            $date = CarbonImmutable::parse($game->match_start)->startOfDay();
            $profile = $this->demand->forClub($home, $date);
            $stands = $this->stadiumCapacity->standsAtDate($stadium, $date);
            $capacity = $this->calculator->availableCapacity((int) $stadium->active_capacity, $stands);
            $amounts = $this->calculator->settlement($capacity, $profile->club_rank, (int) $away->rank,
                (int) $competition->rank, $ticketPrice, $this->repository->venues($stadium), $ticketType, $seasonTicketHolders,
                $profile->country_rank, $profile->performance_score, $profile->ticket_price_sensitivity, $profile->commercial_price_sensitivity);
            $amounts += $this->seasonTickets->constructionRefund($seasonTicketSale, $capacity, array_sum(array_column($stands, 'construction_capacity')));
            $account = $this->repository->accountForUpdate($home);

            foreach (['gate_receipts', 'commercial_income'] as $category) {
                if ($amounts[$category] > 0) {
                    $this->accounting->income($account, $amounts[$category], $date, $category, 'matchday:'.$game->id.':'.$category);
                }
            }
            $this->accounting->expense($account, $amounts['operating_costs'], $date, 'matchday_operating_costs', 'matchday:'.$game->id.':operating_costs');
            if ($amounts['season_ticket_refund_amount'] > 0) {
                $this->accounting->expense($account, $amounts['season_ticket_refund_amount'], $date,
                    'season_ticket_refunds', 'matchday:'.$game->id.':season_ticket_refund');
            }

            return $this->repository->recordSettlement($game, $date->toDateString(), $amounts);
        });
    }
}
