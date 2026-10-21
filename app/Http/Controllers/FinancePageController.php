<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClubFinancialSummaryResource;
use App\Http\Resources\FinanceEntityLoanResource;
use App\Http\Resources\SponsorshipContractResource;
use App\Models\Instance;
use App\Models\Season;
use App\Services\FinanceService\FinanceService;
use App\Services\FinanceService\SponsorshipService;
use App\Support\GameContext;
use Inertia\Inertia;
use Inertia\Response;

class FinancePageController extends Controller
{
    public function show(FinanceService $finances, GameContext $context, SponsorshipService $sponsorships): Response
    {
        $instance = Instance::query()->with('club')->findOrFail($context->instanceId());
        $summary = $finances->getClubFinances();

        return Inertia::render('Finance', [
            'club' => $instance->club->only(['id', 'name']),
            'season' => Season::query()->where('instance_id', $instance->id)
                ->findOrFail($context->seasonId())->only(['start_date', 'end_date']),
            'finances' => $summary === null ? null : (new ClubFinancialSummaryResource($summary))->resolve(),
            'loans' => $summary === null ? [] : FinanceEntityLoanResource::collection($finances->getClubLoans())->resolve(),
            'loanOptions' => $finances->cashLoanOptions(),
            'sponsors' => SponsorshipContractResource::collection($sponsorships->getClubContracts())->resolve(),
        ]);
    }
}
