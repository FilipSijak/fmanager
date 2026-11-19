<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowseMatchdayFinancesRequest;
use App\Http\Resources\ClubFinancialSummaryResource;
use App\Http\Resources\FinanceEntityLoanResource;
use App\Http\Resources\SponsorshipContractResource;
use App\Http\Resources\SponsorshipOfferResource;
use App\Models\Instance;
use App\Models\Season;
use App\Services\FinanceService\FinanceService;
use App\Services\FinanceService\SponsorshipOfferService;
use App\Services\FinanceService\SponsorshipService;
use App\Services\MatchdayService\MatchdayFinanceService;
use App\Support\GameContext;
use Inertia\Inertia;
use Inertia\Response;

class FinancePageController extends Controller
{
    public function show(BrowseMatchdayFinancesRequest $request, FinanceService $finances, GameContext $context, SponsorshipService $sponsorships, SponsorshipOfferService $offers, MatchdayFinanceService $matchdays): Response
    {
        $instance = Instance::query()->with('club')->findOrFail($context->instanceId());
        abort_if($instance->club === null || (int) $instance->club->instance_id !== $instance->id, 404);
        $summary = $finances->getClubFinances();

        return Inertia::render('Finance', [
            'club' => $instance->club->only(['id', 'name']),
            'season' => Season::query()->where('instance_id', $instance->id)
                ->findOrFail($context->seasonId())->only(['start_date', 'end_date']),
            'finances' => $summary === null ? null : (new ClubFinancialSummaryResource($summary))->resolve(),
            'loans' => $summary === null ? [] : FinanceEntityLoanResource::collection($finances->getClubLoans())->resolve(),
            'loanOptions' => $finances->cashLoanOptions(),
            'sponsors' => SponsorshipContractResource::collection($sponsorships->getClubContracts())->resolve(),
            'sponsorOffers' => SponsorshipOfferResource::collection($offers->pendingForManagedClub())->resolve(),
            'sponsorOfferHistory' => SponsorshipOfferResource::collection($offers->historyForManagedClub())->resolve(),
            'matchdayFinances' => fn (): array => $matchdays->overview($instance->club, $context->seasonId(), $request->integer('matchday_page', 1)),
        ]);
    }
}
