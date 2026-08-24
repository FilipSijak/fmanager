<?php

namespace App\Services\SearchService;

use App\Models\Club;
use App\Models\Player;
use App\Repositories\TransferSearchRepository;
use Illuminate\Database\Eloquent\Collection;

final class TransferSearchService
{
    public function __construct(private readonly TransferSearchRepository $transferSearchRepository) {}

    /** @return Collection<int, Player> */
    public function searchForPlayersByAttributes(Club $club, array $attributes): Collection
    {
        return $this->transferSearchRepository->findPlayersByAttributes($club, $attributes);
    }
}
