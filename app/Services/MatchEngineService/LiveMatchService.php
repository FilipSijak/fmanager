<?php

namespace App\Services\MatchEngineService;

use App\Models\BaseData\BaseFormation;
use App\Models\BaseData\BaseFormationSlot;
use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use App\Models\LiveMatch;
use App\Models\Player;
use App\Services\ClubService\ClubService;
use App\Services\GameService\CompleteGameService;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\SideState;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\TacticsService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The user's own match, played live: kicked off on match day, played a few minutes at a time with the user making
 * substitutions in between, and completed like any other game at full time.
 */
class LiveMatchService
{
    public function __construct(
        private readonly MatchTeamBuilder $matchTeamBuilder,
        private readonly MatchSimulationEngine $engine,
        private readonly ClubService $clubService,
        private readonly CompleteGameService $completeGameService,
        private readonly TacticsService $tacticsService,
    ) {}

    /** The user's club's game on the instance's current date that is still to be played. */
    public function userGameToday(Instance $instance): ?Game
    {
        if ($instance->club_id === null) {
            return null;
        }

        return Game::query()
            ->where('instance_id', $instance->id)
            ->forClub($instance->club_id)
            ->whereDate('match_start', $instance->instance_date)
            ->whereNull('processed_at')
            ->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_POSTPONED, Game::STATUS_IN_PROGRESS])
            ->first();
    }

    /** Kicks the user's game off, or returns it if it is already being played. */
    public function start(Game $game, Instance $instance): LiveMatch
    {
        return DB::transaction(function () use ($game, $instance): LiveMatch {
            $existing = LiveMatch::query()->where('game_id', $game->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $matchDate = Carbon::parse($game->match_start)->toDateString();
            $opponentId = $game->hometeam_id === $instance->club_id ? $game->awayteam_id : $game->hometeam_id;
            $this->clubService->selectAiLineup(Club::query()->findOrFail($opponentId), $matchDate);
            $this->ensureUserTactic(Club::query()->findOrFail($instance->club_id));

            $userSide = $game->hometeam_id === $instance->club_id ? 'home' : 'away';
            $state = $this->engine->kickOff(
                $this->matchTeamBuilder->forClub($game->hometeam_id, $matchDate),
                $this->matchTeamBuilder->forClub($game->awayteam_id, $matchDate),
                userSide: $userSide,
            );
            $this->layOutInFormation($state, $userSide);

            $game->status = Game::STATUS_IN_PROGRESS;
            $game->save();

            return LiveMatch::query()->create([
                'instance_id' => $instance->id,
                'game_id' => $game->id,
                'state' => $state->toArray(),
            ]);
        });
    }

    /** The user's game being played, or finished, in this instance. */
    public function find(int $gameId, Instance $instance): LiveMatch
    {
        $liveMatch = LiveMatch::query()->forInstance($instance->id)->where('game_id', $gameId)->first();

        if ($liveMatch === null) {
            throw new DomainException('This match is not being played live.');
        }

        return $liveMatch;
    }

    /** Plays on by the given minutes and completes the game once it reaches full time. */
    public function advance(LiveMatch $liveMatch, int $minutes): LiveMatch
    {
        return DB::transaction(function () use ($liveMatch, $minutes): LiveMatch {
            $state = MatchState::fromArray($liveMatch->state);

            if ($state->isFinished()) {
                return $liveMatch;
            }

            $this->engine->play($state, $state->minute + $minutes);
            $liveMatch->update(['state' => $state->toArray()]);

            if ($state->isFinished()) {
                $result = $this->engine->finish($state);
                $this->completeGameService->complete($liveMatch->game_id, $result->homeGoals, $result->awayGoals, $result->summary, $result->playerStats);
            }

            return $liveMatch;
        });
    }

    public function substitute(LiveMatch $liveMatch, int $playerOffId, int $playerOnId): LiveMatch
    {
        $state = MatchState::fromArray($liveMatch->state);
        $this->engine->substitute($state, $this->userSide($state), $playerOffId, $playerOnId);
        $liveMatch->update(['state' => $state->toArray()]);

        return $liveMatch;
    }

    /**
     * Moves the user's players into the given formation and mentality. Only players already on the pitch can be
     * placed; the formation's positions nobody is placed in stay empty.
     *
     * @param  array<string, ?int>  $assignments  Player id for each formation slot id.
     */
    public function changeTactics(LiveMatch $liveMatch, int $formationId, Mentality $mentality, array $assignments): LiveMatch
    {
        $state = MatchState::fromArray($liveMatch->state);
        $formation = BaseFormation::query()->with('slots')->where('is_active', true)->find($formationId);

        if ($formation === null) {
            throw new DomainException('The selected formation is not available.');
        }

        $this->engine->changeTactics(
            $state,
            $this->userSide($state),
            $formation->id,
            $formation->tactical_tendency,
            $mentality,
            $formation->slots->map(fn (BaseFormationSlot $slot): array => [
                'position' => $slot->position,
                'player_id' => $assignments[$slot->slot] ?? null,
            ])->all(),
        );
        $liveMatch->update(['state' => $state->toArray()]);

        return $liveMatch;
    }

    /**
     * Everything the match screen shows: the score and clock, the events, team totals and each player's stats.
     *
     * @return array<string, mixed>
     */
    public function view(LiveMatch $liveMatch): array
    {
        $state = MatchState::fromArray($liveMatch->state);
        $playerIds = array_merge(...array_map(fn (SideState $side): array => array_keys($side->players), array_values($state->sides)));
        $names = Player::query()->with('person')->whereIn('id', $playerIds)->get()
            ->mapWithKeys(fn (Player $player): array => [$player->id => trim($player->first_name.' '.$player->last_name)]);
        $clubNames = Club::query()->whereIn('id', [$state->side('home')->clubId, $state->side('away')->clubId])->pluck('name', 'id');

        $sides = [];

        foreach (['home', 'away'] as $side) {
            $sides[$side] = $this->sideView($state, $side, $clubNames[$state->side($side)->clubId] ?? '', $names->all());
        }

        return [
            'game_id' => $liveMatch->game_id,
            'minute' => $state->minute,
            'finished' => $state->isFinished(),
            'user_side' => $this->userSide($state),
            'max_substitutions' => Substitutions::MAX_SUBSTITUTIONS,
            'home' => $sides['home'],
            'away' => $sides['away'],
            'events' => array_map(fn (array $event): array => [
                ...$event,
                'player_name' => isset($event['player_id']) ? ($names[$event['player_id']] ?? null) : null,
                'assist_player_name' => isset($event['assist_player_id']) ? ($names[$event['assist_player_id']] ?? null) : null,
                'replaced_player_name' => isset($event['replaced_player_id']) ? ($names[$event['replaced_player_id']] ?? null) : null,
            ], $this->sortedEvents($state)),
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function sideView(MatchState $state, string $side, string $clubName, array $names): array
    {
        $team = $state->side($side);
        $rows = collect($this->engine->playerStatRows($state, $side))->keyBy('player_id');
        $player = fn (int $playerId, string $status): array => [
            'player_id' => $playerId,
            'name' => $names[$playerId] ?? 'Unknown player',
            'position' => $team->players[$playerId]->position,
            'status' => $status,
            'stats' => $rows[$playerId] ?? null,
        ];
        $onPitch = array_keys($team->onPitch());
        $benchUsed = array_values(array_diff(array_keys($team->stats), $onPitch));

        $passesAttempted = $rows->sum('passes_attempted');
        $opponentPasses = collect($this->engine->playerStatRows($state, $side === 'home' ? 'away' : 'home'))->sum('passes_attempted');

        return [
            'club_id' => $team->clubId,
            'name' => $clubName,
            'formation_id' => $team->formationId,
            'mentality' => $team->mentality->value,
            'goals' => $team->goals,
            'substitutions_used' => $team->substitutionsUsed,
            'totals' => [
                'possession' => $passesAttempted + $opponentPasses > 0 ? (int) round(100 * $passesAttempted / ($passesAttempted + $opponentPasses)) : 50,
                'chances' => $team->chances,
                'shots_on_target' => $rows->sum('shots_on_target'),
                'key_passes' => $rows->sum('key_passes'),
                'passes_attempted' => $passesAttempted,
                'passes_completed' => $rows->sum('passes_completed'),
                'tackles_attempted' => $rows->sum('tackles_attempted'),
                'tackles_won' => $rows->sum('tackles_won'),
                'interceptions' => $rows->sum('interceptions'),
                'fouls' => $rows->sum('fouls_committed'),
                'yellow_cards' => $rows->sum('yellow_cards'),
                'red_cards' => $rows->sum('red_cards'),
                'offsides' => $rows->sum('offsides'),
                'saves' => $rows->sum('saves'),
            ],
            'on_pitch' => array_map(fn (array $slot): array => $slot['player_id'] === null
                ? ['player_id' => null, 'name' => null, 'position' => $slot['position'], 'status' => 'empty', 'stats' => null]
                : $player($slot['player_id'], 'playing'), $team->slots),
            'bench' => array_map(fn (int $playerId): array => $player($playerId, 'available'), $team->bench),
            'finished' => array_map(fn (int $playerId): array => $player($playerId, in_array($playerId, $team->sentOff, true) ? 'sent_off' : 'substituted'), $benchUsed),
        ];
    }

    /** @return list<array<string, int|string|null>> */
    private function sortedEvents(MatchState $state): array
    {
        $events = $state->events;
        usort($events, fn (array $first, array $second): int => $first['minute'] <=> $second['minute']);

        return $events;
    }

    /**
     * A user who has never opened the Tactics page has no club tactic yet; it is created from their manager's
     * preference, as opening the page would, so the match has a formation to show and change.
     */
    private function ensureUserTactic(Club $club): void
    {
        try {
            $this->tacticsService->currentForClub($club);
        } catch (DomainException) {
            // A club without a manager plays the engine's default formation.
        }
    }

    /**
     * Puts the user's players in their formation's slot order, so the match screen can draw them on the pitch: each
     * slot takes a player picked for that position, and anyone left over fills the remaining slots in order.
     */
    private function layOutInFormation(MatchState $state, string $side): void
    {
        $team = $state->side($side);
        $formation = $team->formationId === null ? null : BaseFormation::query()->with('slots')->find($team->formationId);

        if ($formation === null) {
            return;
        }

        $remaining = $team->slots;
        $placed = [];

        foreach ($formation->slots as $index => $formationSlot) {
            foreach ($remaining as $key => $slot) {
                if ($slot['position'] === $formationSlot->position) {
                    $placed[$index] = $slot['player_id'];
                    unset($remaining[$key]);

                    break;
                }
            }
        }

        $leftOver = array_column($remaining, 'player_id');
        $slots = $formation->slots->values()->map(fn (BaseFormationSlot $formationSlot, int $index): array => [
            'position' => $formationSlot->position,
            'player_id' => array_key_exists($index, $placed) ? $placed[$index] : array_shift($leftOver),
        ])->all();

        $this->engine->changeTactics($state, $side, $formation->id, $team->formationTendency, $team->mentality, $slots);
    }

    private function userSide(MatchState $state): string
    {
        foreach (['home', 'away'] as $side) {
            if ($state->side($side)->userControlled) {
                return $side;
            }
        }

        throw new DomainException('Neither side of this match is managed by the user.');
    }
}
