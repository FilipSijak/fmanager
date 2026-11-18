<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchdaySettlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'game_id' => (int) $this->game_id,
            'match_start' => $this->match_start,
            'opponent_id' => (int) $this->opponent_id,
            'opponent_name' => $this->opponent_name,
            'competition_name' => $this->competition_name,
            'home_goals' => $this->home_team_goals === null ? null : (int) $this->home_team_goals,
            'away_goals' => $this->away_team_goals === null ? null : (int) $this->away_team_goals,
            'attendance' => $this->attendance,
            'season_ticket_attendance' => $this->season_ticket_attendance,
            'paying_attendance' => $this->paying_attendance,
            'ticket_type' => $this->ticket_type?->value,
            'ticket_price' => $this->ticket_price,
            'gate_receipts' => $this->gate_receipts,
            'commercial_income' => $this->commercial_income,
            'operating_costs' => $this->operating_costs,
            'season_ticket_refund_quantity' => $this->season_ticket_refund_quantity,
            'season_ticket_refund_amount' => $this->season_ticket_refund_amount,
            'net_income' => $this->gate_receipts + $this->commercial_income - $this->operating_costs - $this->season_ticket_refund_amount,
            'settled_on' => $this->settled_on->toDateString(),
        ];
    }
}
