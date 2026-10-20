<?php

namespace App\Services\SearchService;

use App\Models\Player;
use App\Repositories\ElasticsearchPlayerSearchRepository;
use App\Repositories\PlayerSearchRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    public function __construct(
        private readonly PlayerSearchRepository $playerSearchRepository,
        private readonly ElasticsearchPlayerSearchRepository $elasticsearchPlayerSearchRepository,
    ) {}

    /** @return Collection<int, Player> */
    public function searchForPlayersByAttributes(array $attributes): Collection
    {
        return $this->playerSearchRepository->findByAttributes($attributes);
    }

    /** @return LengthAwarePaginator<int, Player> */
    public function searchPlayersByName(string $query, int $limit = 20, int $page = 1): LengthAwarePaginator
    {
        return $this->elasticsearchPlayerSearchRepository->searchByName($query, $limit, $page);
    }

    public function playerComparison(Player $playerOne, Player $playerTwo) {}
}
