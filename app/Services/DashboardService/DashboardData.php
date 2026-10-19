<?php

namespace App\Services\DashboardService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\StaffCoaching;
use Illuminate\Database\Eloquent\Collection;

readonly class DashboardData
{
    public function __construct(
        public Instance $instance,
        public ?Club $club,
        public ?Competition $followedCompetition,
        public ?StaffCoaching $manager,
        public ?Account $account,
        public Collection $news,
        public ?Game $nextGame
    ) {}
}
