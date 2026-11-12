<?php

namespace App\Services\SeasonTicketService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Repositories\SeasonTicketRepository;
use App\Services\AccountingService\AccountingService;
use App\Services\CommercialService\CommercialService;
use App\Services\CommercialService\Domain\TicketType;
use App\Services\MatchdayService\Domain\MatchdayCalculator;
use App\Services\SeasonTicketService\Domain\SeasonTicketCalculator;
use Carbon\CarbonImmutable;

class SeasonTicketService
{
    public function __construct(
        private readonly SeasonTicketRepository $repository,
        private readonly SeasonTicketCalculator $calculator,
        private readonly MatchdayCalculator $matchdayCalculator,
        private readonly CommercialService $commercial,
        private readonly AccountingService $accounting,
    ) {}

    public function sellForSeason(Instance $instance): void
    {
        $season = $this->repository->season($instance->id, $instance->season_id);
        $date = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        foreach ($this->repository->clubs($instance) as $club) {
            $this->sellForClub($club, $season, $date);
        }
    }

    public function holdersForMatch(Club $club, Game $game, Competition $competition): int
    {
        if (TicketType::forCompetition($competition->type, $competition->competition_scope ?? 'domestic') !== TicketType::LEAGUE) {
            return 0;
        }
        $season = $this->repository->season($game->instance_id, $game->season_id);
        $date = CarbonImmutable::parse($game->match_start)->startOfDay();
        $sale = $this->sellForClub($club, $season, $date, $game->id);

        return $sale !== null && $sale->sold_on->lessThanOrEqualTo($date) ? $sale->quantity : 0;
    }

    private function sellForClub(Club $club, Season $season, CarbonImmutable $date, ?int $currentGameId = null): ?SeasonTicketSale
    {
        return $this->repository->transaction(function () use ($club, $season, $date, $currentGameId): ?SeasonTicketSale {
            $club = $this->repository->lockClub($club);
            $existing = $this->repository->sale($club, $season);
            if ($existing !== null) {
                return $existing;
            }
            $homeGames = $this->repository->remainingHomeGames($club, $season, $date->toDateString(), $currentGameId);
            if ($homeGames === 0) {
                return null;
            }
            $stadium = $this->commercial->initializeStadium($this->repository->stadium($club));
            $capacity = $this->matchdayCalculator->availableCapacity((int) $stadium->active_capacity, $this->repository->stands($stadium));
            $offer = $this->calculator->offer($capacity, (int) $stadium->ticket_price, $homeGames);
            $transaction = $offer['amount'] > 0 ? $this->accounting->income(
                $this->repository->accountForUpdate($club), $offer['amount'], $date,
                'season_ticket_sales', 'season-tickets:'.$club->id.':'.$season->id,
            ) : null;

            return $this->repository->recordSale($club, $season, $stadium, $date->toDateString(), $offer, $transaction);
        });
    }
}
