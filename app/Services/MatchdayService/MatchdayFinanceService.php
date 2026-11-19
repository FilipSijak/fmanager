<?php

namespace App\Services\MatchdayService;

use App\Http\Resources\MatchdaySettlementResource;
use App\Models\Club;
use App\Models\MatchdaySettlement;
use App\Repositories\MatchdayFinanceRepository;

class MatchdayFinanceService
{
    public function __construct(private readonly MatchdayFinanceRepository $repository) {}

    /** @return array{summary: array<string, int>, season_tickets: array<string, int|string>|null, matches: array, pagination: array<string, int|null>} */
    public function overview(Club $club, int $seasonId, int $page): array
    {
        $summary = $this->repository->totals($club, $seasonId);
        $sale = $this->repository->seasonTickets($club, $seasonId);
        $summary['net_income'] = $summary['gate_receipts'] + $summary['commercial_income'] - $summary['operating_costs'] - $summary['season_ticket_refunds'];
        $summary['season_ticket_income'] = $sale?->amount ?? 0;
        $summary['net_including_season_tickets'] = $summary['net_income'] + $summary['season_ticket_income'];
        $matches = $this->repository->matches($club, $seasonId, $page);

        return [
            'summary' => $summary,
            'season_tickets' => $sale === null ? null : [
                'quantity' => $sale->quantity, 'home_games' => $sale->home_games, 'ticket_price' => $sale->ticket_price,
                'league_ticket_price' => $sale->league_ticket_price, 'amount' => $sale->amount, 'sold_on' => $sale->sold_on->toDateString(),
            ],
            'matches' => MatchdaySettlementResource::collection($matches->items())->resolve(),
            'pagination' => ['current_page' => $matches->currentPage(), 'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(), 'total' => $matches->total(), 'from' => $matches->firstItem(), 'to' => $matches->lastItem()],
        ];
    }

    public function forMatch(Club $club, int $gameId): ?MatchdaySettlement
    {
        return $this->repository->match($club, $gameId);
    }
}
