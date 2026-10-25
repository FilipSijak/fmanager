<?php

namespace App\Services\ClubService\SquadLineup;

use App\Models\BaseData\BaseFormationSlot;
use App\Models\Club;
use App\Models\Player;
use App\Services\MatchEngineService\PitchLine;
use App\Services\TacticsService\TacticsService;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Picks and saves the matchday squad of a club the user does not manage.
 */
class AiLineupSelector
{
    private const SUBSTITUTES = 7;

    /** Formation slot positions that players record under a different name. */
    private const SLOT_POSITION_ALIASES = [
        'AM' => 'AMC',
        'DM' => 'DMC',
    ];

    public function __construct(
        private readonly TacticsService $tacticsService,
        private readonly SquadLineup $squadLineup,
    ) {}

    /**
     * Fills every slot of the club's current formation and a bench of up to seven, preferring higher-potential players
     * and leaving out anyone injured on the match date. A club that cannot field eleven fit players has its saved
     * lineup cleared, and a club without a manager to set a tactic is left alone; the match engine picks for both.
     */
    public function selectForClub(Club $club, string $matchDate): void
    {
        try {
            $formationSlots = $this->tacticsService->currentForClub($club)->formation->slots;
        } catch (DomainException) {
            return;
        }

        $available = Player::query()
            ->where('club_id', $club->id)
            ->active()
            ->notInjuredOn($matchDate)
            ->orderByDesc('potential')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->collect();

        $starters = $this->startersForFormation($formationSlots, $available);

        if (count($starters) !== SquadLineup::STARTING_PLAYERS || $formationSlots->count() !== count($starters)) {
            $this->squadLineup->saveForClub($club, []);

            return;
        }

        $this->squadLineup->saveForClub($club, [...$starters, ...$this->bench($available)]);
    }

    /**
     * Fills the slots in passes, from the closest fit to the loosest, so a specialist is never taken by an earlier slot
     * that another player could also fill. Picked players are removed from $available.
     *
     * @param  Collection<int, BaseFormationSlot>  $formationSlots
     * @param  Collection<int, Player>  $available  Ordered by potential, best first.
     * @return list<array{slot: string, player_id: int, position: string}>
     */
    private function startersForFormation(Collection $formationSlots, Collection $available): array
    {
        $fitPasses = [
            fn (BaseFormationSlot $slot, Player $player): bool => $player->position === (self::SLOT_POSITION_ALIASES[$slot->position] ?? $slot->position),
            fn (BaseFormationSlot $slot, Player $player): bool => PitchLine::fromPosition($player->position) === PitchLine::fromPosition($slot->position),
            fn (BaseFormationSlot $slot, Player $player): bool => ($player->position === 'GK') === ($slot->position === 'GK'),
            fn (): bool => true,
        ];
        $picked = [];

        foreach ($fitPasses as $fits) {
            foreach ($formationSlots as $slot) {
                if (isset($picked[$slot->slot])) {
                    continue;
                }

                $player = $available->first(fn (Player $player): bool => $fits($slot, $player));

                if ($player !== null) {
                    $picked[$slot->slot] = ['slot' => $slot->slot, 'player_id' => $player->id, 'position' => $slot->position];
                    $available->forget($player->id);
                }
            }
        }

        return $formationSlots
            ->filter(fn (BaseFormationSlot $slot): bool => isset($picked[$slot->slot]))
            ->map(fn (BaseFormationSlot $slot): array => $picked[$slot->slot])
            ->values()
            ->all();
    }

    /**
     * A backup goalkeeper, then the highest-potential players left.
     *
     * @param  Collection<int, Player>  $available  Ordered by potential, best first.
     * @return list<array{slot: string, player_id: int, position: string}>
     */
    private function bench(Collection $available): array
    {
        $backupGoalkeeper = $available->first(fn (Player $player): bool => $player->position === 'GK');
        $substitutes = collect([$backupGoalkeeper])
            ->filter()
            ->concat($available->except([$backupGoalkeeper?->id])->values())
            ->take(self::SUBSTITUTES)
            ->values();

        return $substitutes->map(fn (Player $player, int $index): array => [
            'slot' => 'SUB'.($index + 1),
            'player_id' => $player->id,
            'position' => SquadLineup::SUBSTITUTE_POSITION,
        ])->all();
    }
}
