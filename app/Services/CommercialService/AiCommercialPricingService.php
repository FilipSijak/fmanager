<?php

namespace App\Services\CommercialService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\AiCommercialPricingRepository;
use App\Repositories\MatchdayRepository;
use App\Repositories\StadiumCapacityRepository;
use App\Services\CommercialService\Domain\AiCommercialPriceCalculator;
use App\Services\CommercialService\Domain\TicketType;
use App\Services\MatchdayService\Domain\MatchdayCalculator;
use Carbon\CarbonImmutable;

class AiCommercialPricingService
{
    public function __construct(
        private readonly AiCommercialPricingRepository $repository,
        private readonly CommercialDemandService $demand,
        private readonly CommercialService $commercial,
        private readonly MatchdayRepository $matchdays,
        private readonly StadiumCapacityRepository $stadiumCapacity,
        private readonly MatchdayCalculator $matchdayCalculator,
        private readonly AiCommercialPriceCalculator $prices,
    ) {}

    public function adjustForMonth(Instance $instance): void
    {
        $date = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        foreach ($this->repository->clubs($instance) as $club) {
            $this->adjustClub($club, $date);
        }
    }

    private function adjustClub(Club $club, CarbonImmutable $date): void
    {
        $this->repository->transaction(function () use ($club, $date): void {
            $instance = $this->repository->instanceForUpdate($club->instance_id);
            $club = $this->repository->clubForUpdate($club);
            if ($instance->club_id !== null && (int) $instance->club_id === $club->id) {
                return;
            }
            $stadium = $this->repository->stadiumForUpdate($club);
            if ($stadium === null || $stadium->id === $this->repository->managedStadiumId($instance)) {
                return;
            }
            if ($stadium->ai_prices_reviewed_on !== null && $stadium->ai_prices_reviewed_on->greaterThanOrEqualTo($date->startOfMonth())) {
                return;
            }
            $profile = $this->demand->forClub($club, $date);
            $stadium = $this->commercial->initializeStadium($stadium);
            $capacity = $this->matchdayCalculator->availableCapacity((int) $stadium->active_capacity, $this->stadiumCapacity->standsAtDate($stadium, $date));
            $fixtures = $this->repository->fixtures($club, $instance, $date);
            if ($fixtures === []) {
                $fixtures = [['away_rank' => $profile->club_rank, 'competition_rank' => $this->repository->leagueRank($club, $instance),
                    'ticket_type' => TicketType::LEAGUE, 'season_ticket_holders' => 0]];
            }
            $fixtures = array_map(fn (array $fixture): array => $fixture + ['capacity' => $capacity], $fixtures);
            $proposal = $this->prices->prices((int) $stadium->ticket_price, $this->matchdays->venues($stadium), $fixtures, [
                'club_rank' => $profile->club_rank, 'country_rank' => $profile->country_rank,
                'performance_score' => $profile->performance_score, 'ticket_price_sensitivity' => $profile->ticket_price_sensitivity,
                'commercial_price_sensitivity' => $profile->commercial_price_sensitivity,
            ]);
            $this->repository->storePrices($stadium, $proposal['ticket_price'], $proposal['product_prices'], $date);
        });
    }
}
