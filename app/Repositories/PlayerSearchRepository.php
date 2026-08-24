<?php

namespace App\Repositories;

use App\Models\Player;
use App\Services\SearchService\PlayerSearchQuery;
use Illuminate\Database\Eloquent\Collection;

final class PlayerSearchRepository
{
    public function __construct(private readonly PlayerSearchQuery $playerSearchQuery) {}

    /** @return Collection<int, Player> */
    public function findByAttributes(array $attributes): Collection
    {
        return $this->playerSearchQuery
            ->activeMatchingAttributes($attributes)
            ->get();
    }
}
