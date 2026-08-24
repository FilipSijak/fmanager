<?php

namespace App\Services\SearchService;

use App\Models\Club;
use App\Models\Player;
use App\Repositories\TransferSearchRepository;
use Illuminate\Database\Eloquent\Collection;

class SearchService
{
    public function __construct(
        private readonly TransferSearchRepository $transferSearchRepository,
        private readonly PlayerSearchQuery $playerSearchQuery,
    ) {}

    /** @return Collection<int, Player> */
    public function searchForPlayersByAttributes(array $playerAttributes): Collection
    {
        return $this->playerSearchQuery
            ->activeMatchingAttributes($this->searchableAttributes($playerAttributes))
            ->get();
    }

    /** @return Collection<int, Player> */
    public function transferSearchForPlayerByAttributes(Club $club, array $playerAttributes): Collection
    {
        return $this->transferSearchRepository->findPlayersByAttributes(
            $club,
            $this->searchableAttributes($playerAttributes),
        );
    }

    public function playerComparison(Player $playerOne, Player $playerTwo) {}

    private function searchableAttributes(array $playerAttributes): array
    {
        $attributes = current($playerAttributes);

        if (! is_array($attributes)) {
            return [];
        }

        return array_filter(
            $attributes,
            static fn ($value): bool => (bool) $value,
        );
    }
}
