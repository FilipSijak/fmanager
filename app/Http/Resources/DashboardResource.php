<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $instance = $this->resource['instance'];
        $club = $this->resource['club'];
        $account = $this->resource['account'];
        $news = $this->resource['news'];

        return [
            'instance' => [
                'id' => $instance->id,
                'date' => $instance->instance_date,
                'season_id' => $instance->season_id,
            ],
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'rank' => $club->rank,
                'rank_academy' => $club->rank_academy,
                'rank_training' => $club->rank_training,
            ],
            'account' => [
                'balance' => $account?->balance,
                'future_balance' => $account?->future_balance,
                'transfer_budget' => $account?->transfer_budget,
                'salaries_yearly_budget' => $account?->salaries_yearly_budget,
            ],
            'news' => $news->values()->toArray(),
        ];
    }
}
