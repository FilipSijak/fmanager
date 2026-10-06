<?php

namespace App\Services\TacticsService;

use App\Models\Club;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Player;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SquadLineupService
{
    public function __construct(
        private readonly TacticsService $tacticsService,
    ) {}

    /** @return Collection<int, ClubTacticPlayerSlot> */
    public function currentForClub(Club $club): Collection
    {
        $tactic = $this->tacticsService->currentForClub($club);

        return ClubTacticPlayerSlot::query()->where('club_tactic_id', $tactic->id)->get();
    }

    /**
     * @param  list<array{slot: string, player_id: int, position: string}>  $assignments
     * @return Collection<int, ClubTacticPlayerSlot>
     */
    public function saveForClub(Club $club, array $assignments): Collection
    {
        return DB::transaction(function () use ($club, $assignments): Collection {
            $this->validatePlayersBelongToClub($club, $assignments);

            $tactic = $this->tacticsService->currentForClub($club);

            ClubTacticPlayerSlot::query()->where('club_tactic_id', $tactic->id)->delete();

            $rows = array_map(fn (array $assignment): array => [
                'instance_id' => $club->instance_id,
                'club_id' => $club->id,
                'club_tactic_id' => $tactic->id,
                'player_id' => $assignment['player_id'],
                'slot' => $assignment['slot'],
                'position' => $assignment['position'],
                'created_at' => now(),
                'updated_at' => now(),
            ], $assignments);

            if ($rows !== []) {
                ClubTacticPlayerSlot::query()->insert($rows);
            }

            return ClubTacticPlayerSlot::query()->where('club_tactic_id', $tactic->id)->get();
        });
    }

    /** @param list<array{slot: string, player_id: int, position: string}> $assignments */
    private function validatePlayersBelongToClub(Club $club, array $assignments): void
    {
        $playerIds = array_unique(array_column($assignments, 'player_id'));

        if ($playerIds === []) {
            return;
        }

        $belongingCount = Player::query()
            ->where('instance_id', $club->instance_id)
            ->where('club_id', $club->id)
            ->where('is_retired', false)
            ->whereIn('id', $playerIds)
            ->count();

        if ($belongingCount !== count($playerIds)) {
            throw new DomainException('One or more selected players do not belong to this club.');
        }
    }
}
