<?php

namespace App\Services\CommercialService;

use App\Models\Club;
use App\Models\ClubDemandProfile;
use App\Models\Instance;
use App\Repositories\TicketDemandRepository;
use App\Services\CommercialService\Domain\TicketDemandCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CommercialDemandService
{
    public function __construct(
        private readonly TicketDemandRepository $repository,
        private readonly TicketDemandCalculator $calculator,
    ) {}

    public function refreshForMonth(Instance $instance): void
    {
        $date = CarbonImmutable::parse($instance->instance_date);
        foreach ($this->repository->clubs($instance) as $club) {
            $this->forClub($club, $date);
        }
    }

    public function forClub(Club $club, CarbonInterface $date): ClubDemandProfile
    {
        $periodStart = CarbonImmutable::instance($date)->startOfMonth();
        $profile = $this->repository->profile($club, $periodStart);
        if ($profile !== null) {
            return $profile;
        }

        return $this->repository->transaction(function () use ($club, $periodStart): ClubDemandProfile {
            $club = $this->repository->lockClub($club);
            $profile = $this->repository->profile($club, $periodStart);
            if ($profile !== null) {
                return $profile;
            }
            $inputs = $this->repository->inputs($club, $periodStart);
            $performance = $this->calculator->performanceScore($club->id, $inputs['results']);
            $sensitivity = $this->calculator->priceSensitivity($inputs['country_rank'], (int) $club->rank, $performance);

            return $this->repository->recordProfile($club, $periodStart, [
                'club_rank' => (int) $club->rank,
                'country_rank' => $inputs['country_rank'],
                'performance_score' => $performance,
                'ticket_price_sensitivity' => $sensitivity,
                'commercial_price_sensitivity' => $sensitivity,
            ]);
        });
    }
}
