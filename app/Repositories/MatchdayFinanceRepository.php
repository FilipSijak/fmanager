<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Game;
use App\Models\MatchdaySettlement;
use App\Models\SeasonTicketSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MatchdayFinanceRepository
{
    public function matches(Club $club, int $seasonId, int $page): LengthAwarePaginator
    {
        return $this->settlements($club)->where('games.season_id', $seasonId)
            ->orderByDesc('games.match_start')->orderByDesc('matchday_settlements.game_id')
            ->paginate(10, $this->columns(), 'matchday_page', $page);
    }

    public function match(Club $club, int $gameId): ?MatchdaySettlement
    {
        return $this->settlements($club)->where('matchday_settlements.game_id', $gameId)->first($this->columns());
    }

    /** @return array{matches: int, attendance: int, average_attendance: int, gate_receipts: int, commercial_income: int, operating_costs: int, season_ticket_refunds: int} */
    public function totals(Club $club, int $seasonId): array
    {
        $totals = $this->settlements($club)->where('games.season_id', $seasonId)
            ->selectRaw('COUNT(*) AS matches_count, COALESCE(SUM(matchday_settlements.attendance), 0) AS total_attendance,
                COALESCE(AVG(matchday_settlements.attendance), 0) AS average_attendance,
                COALESCE(SUM(gate_receipts), 0) AS total_gate, COALESCE(SUM(commercial_income), 0) AS total_commercial,
                COALESCE(SUM(operating_costs), 0) AS total_costs, COALESCE(SUM(season_ticket_refund_amount), 0) AS total_refunds')->first();

        return ['matches' => (int) $totals->matches_count, 'attendance' => (int) $totals->total_attendance,
            'average_attendance' => (int) round((float) $totals->average_attendance), 'gate_receipts' => (int) $totals->total_gate,
            'commercial_income' => (int) $totals->total_commercial, 'operating_costs' => (int) $totals->total_costs,
            'season_ticket_refunds' => (int) $totals->total_refunds];
    }

    public function seasonTickets(Club $club, int $seasonId): ?SeasonTicketSale
    {
        return SeasonTicketSale::query()->forClub($club)->where('season_id', $seasonId)->first();
    }

    private function settlements(Club $club): Builder
    {
        return MatchdaySettlement::query()->where('matchday_settlements.instance_id', $club->instance_id)
            ->where('matchday_settlements.club_id', $club->id)
            ->join('games', 'games.id', '=', 'matchday_settlements.game_id')
            ->where('games.instance_id', $club->instance_id)->where('games.hometeam_id', $club->id)
            ->where('games.status', Game::STATUS_COMPLETED)
            ->join('clubs as opponent', 'opponent.id', '=', 'games.awayteam_id')->where('opponent.instance_id', $club->instance_id)
            ->join('competitions', 'competitions.id', '=', 'games.competition_id')->where('competitions.instance_id', $club->instance_id);
    }

    /** @return list<string> */
    private function columns(): array
    {
        return ['matchday_settlements.*', 'games.match_start', 'games.home_team_goals', 'games.away_team_goals',
            'opponent.id as opponent_id', 'opponent.name as opponent_name', 'competitions.name as competition_name'];
    }
}
