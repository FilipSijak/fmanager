<?php

namespace App\Http\Resources;

use App\Services\DashboardService\DashboardData;
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
        /** @var DashboardData $dashboard */
        $dashboard = $this->resource;

        return [
            'instance' => [
                'id' => $dashboard->instance->id,
                'date' => $dashboard->instance->instance_date,
                'season_id' => $dashboard->instance->season_id,
            ],
            'club' => [
                'id' => $dashboard->club->id,
                'name' => $dashboard->club->name,
                'rank' => $dashboard->club->rank,
                'rank_academy' => $dashboard->club->rank_academy,
                'rank_training' => $dashboard->club->rank_training,
            ],
            'account' => [
                'balance' => $dashboard->account?->balance,
                'future_balance' => $dashboard->account?->future_balance,
                'transfer_budget' => $dashboard->account?->transfer_budget,
                'salaries_yearly_budget' => $dashboard->account?->salaries_yearly_budget,
            ],
            'news' => NewsResource::collection($dashboard->news)->resolve($request),
        ];
    }
}
