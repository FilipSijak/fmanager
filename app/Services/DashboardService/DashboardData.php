<?php

namespace App\Services\DashboardService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Collection;

readonly class DashboardData
{
    public function __construct(
        public Instance $instance,
        public Club $club,
        public ?Account $account,
        public Collection $news,
        public ?Game $nextMatch
    ) {
    }
}
