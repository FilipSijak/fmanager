<?php

namespace App\Services\SearchService;

use App\Models\Club;
use App\Models\Player;

use App\Repositories\TransferSearchRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchService
{
    private TransferSearchRepository $transferSearchRepository;

    public function __construct(TransferSearchRepository $transferSearchRepository)
    {
        $this->transferSearchRepository = $transferSearchRepository;
    }

    public function transferSearchForPlayerByAttributes(Club $club, array $playerAttributes): Collection
    {
        $searchableAttribute = [];

        foreach (current($playerAttributes) as $attribute => $value) {
            if ($value) {
                $searchableAttribute[$attribute] = $value;
            }
        }

        return $this->transferSearchRepository->playersByAttributes($club, $searchableAttribute);
    }

    public function playerComparison(Player $playerOne, Player $playerTwo)
    {

    }
}
