<?php

namespace App\Services\SearchService;

use App\Models\Player;
use App\Repositories\PlayerSearchRepository;
use Illuminate\Database\Eloquent\Collection;

class SearchService
{
    public function __construct(private readonly PlayerSearchRepository $playerSearchRepository) {}

    /** @return Collection<int, Player> */
    public function searchForPlayersByAttributes(array $attributes): Collection
    {
        return $this->playerSearchRepository->findByAttributes($attributes);
    }

    public function playerComparison(Player $playerOne, Player $playerTwo) {}
}
