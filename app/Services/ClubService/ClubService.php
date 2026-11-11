<?php

namespace App\Services\ClubService;

use App\Models\Club;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Instance;
use App\Services\ClubService\SquadLineup\AiLineupSelector;
use App\Services\ClubService\SquadLineup\SquadLineup;
use Illuminate\Database\Eloquent\Collection;

class ClubService
{
    public function __construct(
        private readonly SquadLineup $squadLineup,
        private readonly AiLineupSelector $aiLineupSelector,
        private readonly AnnualYouthIntake $youthIntake,
    ) {}

    public function annualYouthIntake(Instance $instance): void
    {
        $this->youthIntake->process($instance);
    }

    /** @return Collection<int, ClubTacticPlayerSlot> */
    public function currentLineup(Club $club): Collection
    {
        return $this->squadLineup->currentForClub($club);
    }

    /**
     * @param  list<array{slot: string, player_id: int, position: string}>  $assignments
     * @return Collection<int, ClubTacticPlayerSlot>
     */
    public function saveLineup(Club $club, array $assignments): Collection
    {
        return $this->squadLineup->saveForClub($club, $assignments);
    }

    /**
     * Picks the matchday squad of a club the user does not manage.
     */
    public function selectAiLineup(Club $club, string $matchDate): void
    {
        $this->aiLineupSelector->selectForClub($club, $matchDate);
    }
}
