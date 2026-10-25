<?php

namespace App\Services\MatchEngineService;

use App\Models\BaseData\BaseFormation;
use App\Models\ClubTactic;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\ClubService\SquadLineup\SquadLineup;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use Illuminate\Support\Collection;

/**
 * Turns a club's tactic and matchday squad into the ratings the match engine plays with.
 */
class MatchTeamBuilder
{
    private const LINEUP_SIZE = SquadLineup::STARTING_PLAYERS;

    private const SUBSTITUTES = 7;

    /** Rating given to a lineup slot the club cannot fill. */
    private const EMPTY_SLOT_RATING = 1.0;

    /** Lines played when the club has no formation: a 4-4-2. */
    private const DEFAULT_LINES = [
        'goalkeeper' => 1,
        'defence' => 4,
        'midfield' => 4,
        'attack' => 2,
    ];

    /** @var array<string, list<string>> */
    private const SKILL_ATTRIBUTES = [
        'goalkeeping' => ['handling', 'reflexes', 'one_on_ones', 'aerial_reach', 'positioning', 'concentration'],
        'defending' => ['marking', 'tackling', 'positioning', 'anticipation', 'concentration', 'heading', 'strength', 'pace'],
        'playmaking' => ['passing', 'first_touch', 'technique', 'decisions', 'creativity', 'teamwork', 'workrate', 'stamina'],
        'attacking' => ['finishing', 'of_the_ball', 'composure', 'dribbling', 'technique', 'pace', 'acceleration', 'heading'],
    ];

    /**
     * How much each line contributes to a team rating, keyed by rating then line.
     *
     * @var array<string, array<string, float>>
     */
    private const LINE_WEIGHTS = [
        'attacking' => ['attack' => 1.0, 'midfield' => 0.5, 'defence' => 0.15],
        'playmaking' => ['midfield' => 1.0, 'attack' => 0.3, 'defence' => 0.3],
        'defending' => ['defence' => 1.0, 'midfield' => 0.5, 'attack' => 0.15],
    ];

    /**
     * Players injured on the match date are unavailable: they are dropped from the saved lineup and replaced like
     * players who have left the club.
     */
    public function forClub(int $clubId, string $matchDate): MatchTeam
    {
        $tactic = ClubTactic::query()->with('formation.slots')->where('club_id', $clubId)->first();
        $preference = $tactic === null ? $this->managerPreference($clubId) : null;
        $formation = $tactic?->formation ?? $preference?->formation;
        $squad = Player::query()
            ->where('club_id', $clubId)
            ->active()
            ->notInjuredOn($matchDate)
            ->get()
            ->keyBy('id')
            ->collect();

        $savedSelection = $this->savedSelection($tactic, $squad);
        [$savedStarters, $savedSubstitutes] = $savedSelection ?? [[], collect()];
        $savedPlayerIds = [
            ...array_map(fn (array $slot): int => $slot['player']->id, $savedStarters),
            ...$savedSubstitutes->keys()->all(),
        ];
        $unselected = $squad->except($savedPlayerIds);

        $lineup = $this->completeLineup($this->formationLines($formation), $savedStarters, $savedSubstitutes, $unselected);
        $starterIds = array_map(fn (array $slot): int => $slot['player']->id, array_filter($lineup, fn (array $slot): bool => $slot['player'] !== null));
        $substitutes = $savedSelection === null
            ? $this->pickBench($unselected->except($starterIds))
            : $savedSubstitutes->except($starterIds);

        return new MatchTeam(
            clubId: $clubId,
            attack: $this->teamRating($lineup, 'attacking'),
            midfield: $this->teamRating($lineup, 'playmaking'),
            defence: $this->teamRating($lineup, 'defending'),
            goalkeeping: $this->goalkeeperRating($lineup),
            formationTendency: $formation?->tactical_tendency ?? FormationTendency::BALANCED,
            mentality: $tactic?->mentality ?? $preference?->mentality ?? Mentality::BALANCED,
            startingPlayerIds: array_values($starterIds),
            substitutePlayerIds: $substitutes->keys()->all(),
        );
    }

    /**
     * Clubs whose tactic has never been opened have no club tactic yet, so their manager's preference is used.
     */
    private function managerPreference(int $clubId): ?StaffTacticPreference
    {
        $manager = StaffCoaching::query()
            ->where('club_id', $clubId)
            ->where('type', PersonTypes::MANAGER)
            ->active()
            ->first();

        if ($manager === null) {
            return null;
        }

        return StaffTacticPreference::query()
            ->with('formation.slots')
            ->where('staff_coaching_id', $manager->id)
            ->first();
    }

    /**
     * The starters and substitutes the club picked who are still in the squad, or null when it has not picked a lineup.
     * Substitutes are optional, so a saved lineup may come with a short or empty bench.
     *
     * @param  Collection<int, Player>  $squad
     * @return array{0: list<array{line: PitchLine, player: Player}>, 1: Collection<int, Player>}|null
     */
    private function savedSelection(?ClubTactic $tactic, Collection $squad): ?array
    {
        if ($tactic === null) {
            return null;
        }

        $savedSlots = ClubTacticPlayerSlot::query()
            ->where('club_tactic_id', $tactic->id)
            ->orderBy('id')
            ->get();

        if ($savedSlots->isEmpty()) {
            return null;
        }

        $slots = $savedSlots->filter(fn (ClubTacticPlayerSlot $slot): bool => $squad->has($slot->player_id));
        [$substituteSlots, $starterSlots] = $slots->partition(fn (ClubTacticPlayerSlot $slot): bool => $slot->position === SquadLineup::SUBSTITUTE_POSITION);

        $starters = $starterSlots->take(self::LINEUP_SIZE)->map(fn (ClubTacticPlayerSlot $slot): array => [
            'line' => PitchLine::fromPosition($slot->position),
            'player' => $squad->get($slot->player_id),
        ])->values()->all();

        $substitutes = $substituteSlots->take(self::SUBSTITUTES)
            ->mapWithKeys(fn (ClubTacticPlayerSlot $slot): array => [$slot->player_id => $squad->get($slot->player_id)])
            ->toBase();

        return [$starters, $substitutes];
    }

    /** @return array<string, int> */
    private function formationLines(?BaseFormation $formation): array
    {
        if ($formation === null || $formation->slots->count() !== self::LINEUP_SIZE) {
            return self::DEFAULT_LINES;
        }

        return $formation->slots
            ->countBy(fn ($slot): string => PitchLine::fromPosition($slot->position)->value)
            ->all();
    }

    /**
     * Keeps the saved starters and fills each line's open places, first from the saved bench and then from the rest
     * of the squad, preferring players who naturally play in that line. Places nobody can fill stay empty.
     *
     * @param  array<string, int>  $lines
     * @param  list<array{line: PitchLine, player: Player}>  $savedStarters
     * @param  Collection<int, Player>  $savedSubstitutes
     * @param  Collection<int, Player>  $unselected
     * @return list<array{line: PitchLine, player: ?Player}>
     */
    private function completeLineup(array $lines, array $savedStarters, Collection $savedSubstitutes, Collection $unselected): array
    {
        $lineup = $savedStarters;

        foreach ([$savedSubstitutes->collect(), $unselected->collect()] as $pool) {
            foreach ([true, false] as $naturalPositionOnly) {
                foreach ($lines as $lineValue => $count) {
                    $line = PitchLine::from($lineValue);
                    $open = min($count - $this->playersInLine($lineup, $line), self::LINEUP_SIZE - count($lineup));
                    $candidates = $pool
                        ->filter(fn (Player $player): bool => ! $naturalPositionOnly || PitchLine::fromPosition($player->position) === $line)
                        ->sortByDesc(fn (Player $player): float => $this->skillRating($player, $this->lineSkill($line)))
                        ->take(max(0, $open));

                    foreach ($candidates as $player) {
                        $lineup[] = ['line' => $line, 'player' => $player];
                        $pool->forget($player->id);
                    }
                }
            }
        }

        foreach ($lines as $lineValue => $count) {
            $line = PitchLine::from($lineValue);

            for ($slot = $this->playersInLine($lineup, $line); $slot < $count; $slot++) {
                $lineup[] = ['line' => $line, 'player' => null];
            }
        }

        return $lineup;
    }

    /**
     * A bench for clubs that have not picked a lineup: a backup goalkeeper, then the best of the remaining players.
     *
     * @param  Collection<int, Player>  $unselected
     * @return Collection<int, Player>
     */
    private function pickBench(Collection $unselected): Collection
    {
        $bench = collect();
        $backupGoalkeeper = $unselected
            ->filter(fn (Player $player): bool => PitchLine::fromPosition($player->position) === PitchLine::GOALKEEPER)
            ->sortByDesc(fn (Player $player): float => $this->skillRating($player, 'goalkeeping'))
            ->first();

        if ($backupGoalkeeper !== null) {
            $bench->put($backupGoalkeeper->id, $backupGoalkeeper);
        }

        $unselected->except($bench->keys()->all())
            ->sortByDesc(fn (Player $player): float => $this->naturalRating($player))
            ->take(self::SUBSTITUTES - $bench->count())
            ->each(fn (Player $player) => $bench->put($player->id, $player));

        return $bench;
    }

    /** @param list<array{line: PitchLine, player: ?Player}> $lineup */
    private function playersInLine(array $lineup, PitchLine $line): int
    {
        return count(array_filter($lineup, fn (array $slot): bool => $slot['line'] === $line));
    }

    /** @param list<array{line: PitchLine, player: ?Player}> $lineup */
    private function teamRating(array $lineup, string $skill): float
    {
        $weightedRating = 0.0;
        $totalWeight = 0.0;

        foreach ($lineup as $slot) {
            $weight = self::LINE_WEIGHTS[$skill][$slot['line']->value] ?? 0.0;
            $weightedRating += $weight * $this->slotRating($slot['player'], $skill);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedRating / $totalWeight : self::EMPTY_SLOT_RATING;
    }

    /** @param list<array{line: PitchLine, player: ?Player}> $lineup */
    private function goalkeeperRating(array $lineup): float
    {
        foreach ($lineup as $slot) {
            if ($slot['line'] === PitchLine::GOALKEEPER) {
                return $this->slotRating($slot['player'], 'goalkeeping');
            }
        }

        return self::EMPTY_SLOT_RATING;
    }

    private function slotRating(?Player $player, string $skill): float
    {
        return $player === null ? self::EMPTY_SLOT_RATING : $this->skillRating($player, $skill);
    }

    private function naturalRating(Player $player): float
    {
        return $this->skillRating($player, $this->lineSkill(PitchLine::fromPosition($player->position)));
    }

    private function skillRating(Player $player, string $skill): float
    {
        $attributes = self::SKILL_ATTRIBUTES[$skill];

        return array_sum(array_map(fn (string $attribute): int => (int) $player->{$attribute}, $attributes)) / count($attributes);
    }

    private function lineSkill(PitchLine $line): string
    {
        return match ($line) {
            PitchLine::GOALKEEPER => 'goalkeeping',
            PitchLine::DEFENCE => 'defending',
            PitchLine::MIDFIELD => 'playmaking',
            PitchLine::ATTACK => 'attacking',
        };
    }
}
