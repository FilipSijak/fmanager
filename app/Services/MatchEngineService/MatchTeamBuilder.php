<?php

namespace App\Services\MatchEngineService;

use App\Models\BaseData\BaseFormation;
use App\Models\ClubTactic;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Player;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns a club's tactic and starting eleven into the ratings the match engine plays with.
 */
class MatchTeamBuilder
{
    private const LINEUP_SIZE = 11;

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

    public function forClub(int $clubId): MatchTeam
    {
        $tactic = ClubTactic::query()->with('formation.slots')->where('club_id', $clubId)->first();
        $preference = $tactic === null ? $this->managerPreference($clubId) : null;
        $formation = $tactic?->formation ?? $preference?->formation;
        $squad = Player::query()
            ->where('club_id', $clubId)
            ->where('is_retired', false)
            ->get()
            ->keyBy('id');

        $lineup = $this->savedLineup($tactic, $squad) ?? $this->pickLineup($this->formationLines($formation), $squad);

        return new MatchTeam(
            clubId: $clubId,
            attack: $this->teamRating($lineup, 'attacking'),
            midfield: $this->teamRating($lineup, 'playmaking'),
            defence: $this->teamRating($lineup, 'defending'),
            goalkeeping: $this->goalkeeperRating($lineup),
            formationTendency: $formation?->tactical_tendency ?? FormationTendency::BALANCED,
            mentality: $tactic?->mentality ?? $preference?->mentality ?? Mentality::BALANCED,
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
     * The lineup the club picked, as long as all eleven players are still in the squad.
     *
     * @param  Collection<int, Player>  $squad
     * @return list<array{line: PitchLine, player: ?Player}>|null
     */
    private function savedLineup(?ClubTactic $tactic, Collection $squad): ?array
    {
        if ($tactic === null) {
            return null;
        }

        $slots = ClubTacticPlayerSlot::query()->where('club_tactic_id', $tactic->id)->get();
        $playerIds = $slots->pluck('player_id')->unique();

        if ($slots->count() !== self::LINEUP_SIZE || $playerIds->count() !== self::LINEUP_SIZE
            || $playerIds->contains(fn (int $playerId): bool => ! $squad->has($playerId))) {
            return null;
        }

        return $slots->map(fn (ClubTacticPlayerSlot $slot): array => [
            'line' => PitchLine::fromPosition($slot->position),
            'player' => $squad->get($slot->player_id),
        ])->values()->all();
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
     * Fills each line with the best players who naturally play there, then fills any gaps with the best of the rest.
     *
     * @param  array<string, int>  $lines
     * @param  Collection<int, Player>  $squad
     * @return list<array{line: PitchLine, player: ?Player}>
     */
    private function pickLineup(array $lines, Collection $squad): array
    {
        $available = $squad->collect();
        $lineup = [];

        foreach ([true, false] as $naturalPositionOnly) {
            foreach ($lines as $lineValue => $count) {
                $line = PitchLine::from($lineValue);
                $needed = $count - count(array_filter($lineup, fn (array $slot): bool => $slot['line'] === $line));
                $candidates = $available
                    ->filter(fn (Player $player): bool => ! $naturalPositionOnly || PitchLine::fromPosition($player->position) === $line)
                    ->sortByDesc(fn (Player $player): float => $this->skillRating($player, $this->lineSkill($line)))
                    ->take(max(0, $needed));

                foreach ($candidates as $player) {
                    $lineup[] = ['line' => $line, 'player' => $player];
                    $available->forget($player->id);
                }
            }
        }

        foreach ($lines as $lineValue => $count) {
            $line = PitchLine::from($lineValue);
            $filled = count(array_filter($lineup, fn (array $slot): bool => $slot['line'] === $line));

            for ($slot = $filled; $slot < $count; $slot++) {
                $lineup[] = ['line' => $line, 'player' => null];
            }
        }

        return $lineup;
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
