<?php

namespace App\Services\MatchdayService;

use App\Models\Game;
use App\Models\MatchdaySettlement;
use App\Repositories\MatchdayRepository;
use App\Repositories\TicketDemandRepository;
use App\Services\AccountingService\AccountingService;
use App\Services\CommercialService\CommercialService;
use App\Services\CommercialService\Domain\TicketDemandCalculator;
use App\Services\CommercialService\Domain\TicketPriceCalculator;
use App\Services\CommercialService\Domain\TicketType;
use App\Services\MatchdayService\Domain\MatchdayCalculator;
use App\Services\SeasonTicketService\SeasonTicketService;
use Carbon\CarbonImmutable;

class MatchdayService
{
    public function __construct(
        private readonly MatchdayRepository $repository,
        private readonly MatchdayCalculator $calculator,
        private readonly AccountingService $accounting,
        private readonly CommercialService $commercial,
        private readonly TicketPriceCalculator $ticketPriceCalculator,
        private readonly SeasonTicketService $seasonTickets,
        private readonly TicketDemandRepository $demandRepository,
        private readonly TicketDemandCalculator $demandCalculator,
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
            $seasonTicketHolders = $this->seasonTickets->holdersForMatch($home, $game, $competition);
            $stadium = $this->commercial->initializeStadium($stadium);
            $ticketType = TicketType::forCompetition($competition->type, $competition->competition_scope ?? 'domestic');
            $ticketPrice = $this->ticketPriceCalculator->forType((int) $stadium->ticket_price, $ticketType);
            $date = CarbonImmutable::parse($game->match_start)->startOfDay();
            $demandInputs = $this->demandRepository->inputs($home, CarbonImmutable::parse($game->match_start));
            $performance = $this->demandCalculator->performanceScore($home->id, $demandInputs['results']);
            $capacity = $this->calculator->availableCapacity((int) $stadium->active_capacity, $this->repository->stands($stadium));
            $amounts = $this->calculator->settlement($capacity, (int) $home->rank, (int) $away->rank,
                (int) $competition->rank, $ticketPrice, $this->repository->venues($stadium), $ticketType, $seasonTicketHolders, $demandInputs['country_rank'], $performance);
            $account = $this->repository->accountForUpdate($home);

            foreach (['gate_receipts', 'commercial_income'] as $category) {
                if ($amounts[$category] > 0) {
                    $this->accounting->income($account, $amounts[$category], $date, $category, 'matchday:'.$game->id.':'.$category);
                }
            }
            $this->accounting->expense($account, $amounts['operating_costs'], $date, 'matchday_operating_costs', 'matchday:'.$game->id.':operating_costs');

            return $this->repository->recordSettlement($game, $date->toDateString(), $amounts);
        });
    }
}
