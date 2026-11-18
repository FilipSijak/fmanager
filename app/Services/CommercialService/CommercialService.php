<?php

namespace App\Services\CommercialService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Stadium;
use App\Repositories\CommercialRepository;
use App\Services\CommercialService\Domain\StadiumCommercialDefaults;
use App\Services\CommercialService\Domain\TicketPriceCalculator;
use App\Services\CommercialService\Domain\TicketType;
use App\Support\GameContext;
use Illuminate\Support\Collection;

class CommercialService
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly CommercialRepository $repository,
        private readonly StadiumCommercialDefaults $defaults,
        private readonly TicketPriceCalculator $ticketPriceCalculator,
    ) {}

    public function initializeStadium(Stadium $stadium): Stadium
    {
        return $this->repository->transaction(function () use ($stadium): Stadium {
            $stadium = $this->repository->lockStadium($stadium);
            if ($stadium->ticket_price === null) {
                $this->repository->storeTicketPrice($stadium, $this->defaults->ticketPrice($this->repository->ticketPriceInputs($stadium)));
            }
            $this->repository->insertProducts($this->defaults->productRows(
                $stadium->instance_id, $stadium->id, $this->repository->missingProducts($stadium),
            ));

            return $stadium;
        });
    }

    public function initializeStadiums(?int $instanceId = null): int
    {
        if ($instanceId !== null) {
            $this->repository->requireInstance($instanceId);
        }
        $processed = 0;
        foreach ($this->repository->stadiums($instanceId) as $stadium) {
            $this->initializeStadium($stadium);
            $processed++;
        }

        return $processed;
    }

    public function ticketPriceForCompetition(Club $club, Competition $competition): int
    {
        $price = $this->initializeStadium($this->repository->stadiumForClub($club))->ticket_price;

        return $this->ticketPriceCalculator->forType($price, TicketType::forCompetition($competition->type, $competition->competition_scope ?? 'domestic'));
    }

    /** @return Collection<int, int> */
    public function ticketPricesForClub(Club $club): Collection
    {
        $price = $this->initializeStadium($this->repository->stadiumForClub($club))->ticket_price;

        return $this->repository->competitionsForClub($club, $this->gameContext->seasonId())
            ->mapWithKeys(fn (Competition $competition): array => [
                $competition->id => $this->ticketPriceCalculator->forType($price, TicketType::forCompetition($competition->type, $competition->competition_scope ?? 'domestic')),
            ]);
    }
}
