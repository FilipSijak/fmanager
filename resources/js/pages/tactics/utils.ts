import type { SquadPlayer } from '../squad/types';
import { SUBSTITUTE_POSITION_LABEL, SUBSTITUTE_SLOT_IDS } from '../squad/utils';
import type {
    Formation,
    LineupPlayer,
    PitchPlayer,
    Tactic,
    TacticsDraft,
} from './types';

export { extractErrorMessage } from '../squad/utils';
export { SUBSTITUTE_SLOT_IDS };

/** Starters are saved under the formation's own slot ids, in formation order (GK first). */
export function starterSlotIds(formation: Formation | undefined): string[] {
    return formation?.slots.map((slot) => slot.slot) ?? [];
}

export function formatOptionLabel(option: string): string {
    return option.charAt(0).toUpperCase() + option.slice(1);
}

function formationSlot(formation: Formation | undefined, slotId: string) {
    return formation?.slots.find((slot) => slot.slot === slotId);
}

/** The position a lineup slot is picked for: the formation slot's position, or SUB for the bench. */
export function lineupPositionForSlot(
    slotId: string,
    formation: Formation | undefined,
): string {
    return (
        formationSlot(formation, slotId)?.position ?? SUBSTITUTE_POSITION_LABEL
    );
}

/** Resolves each slot id to its assigned squad player, numbering them from firstNumber. */
export function lineupPlayersForSlots(
    slotIds: string[],
    assignments: Record<string, number>,
    players: SquadPlayer[],
    formation: Formation | undefined,
    firstNumber: number,
): LineupPlayer[] {
    const playersById = new Map(players.map((player) => [player.id, player]));

    return slotIds.flatMap((slotId, index) => {
        const player = playersById.get(assignments[slotId]);

        return player
            ? [
                  {
                      slotId,
                      number: firstNumber + index,
                      lineupPosition: lineupPositionForSlot(slotId, formation),
                      player,
                  },
              ]
            : [];
    });
}

/**
 * Defensive-to-attacking rank of each formation position. Positions sharing a
 * rank are the same line of the team and are ordered left to right instead.
 */
const POSITION_RANKS: Record<string, number> = {
    GK: 0,
    LB: 1,
    CB: 1,
    RB: 1,
    LWB: 2,
    RWB: 2,
    DM: 3,
    LM: 4,
    CM: 4,
    RM: 4,
    AM: 5,
    LW: 6,
    RW: 6,
    ST: 7,
};

const UNKNOWN_POSITION_RANK = Object.keys(POSITION_RANKS).length;

/** Orders starters from goalkeeper to attack, then left to right within the same line. */
export function sortByLineupPosition(
    lineupPlayers: LineupPlayer[],
    formation: Formation | undefined,
): LineupPlayer[] {
    function pitchX(slotId: string): number {
        return formationSlot(formation, slotId)?.x ?? 0;
    }

    function rank(lineupPlayer: LineupPlayer): number {
        return (
            POSITION_RANKS[lineupPlayer.lineupPosition] ?? UNKNOWN_POSITION_RANK
        );
    }

    return [...lineupPlayers].sort(
        (a, b) => rank(a) - rank(b) || pitchX(a.slotId) - pitchX(b.slotId),
    );
}

/** Returns new assignments with the players in the two slots exchanged. */
export function swapSlotAssignments(
    assignments: Record<string, number>,
    fromSlotId: string,
    toSlotId: string,
): Record<string, number> {
    const next = { ...assignments };
    const fromPlayerId = assignments[fromSlotId];
    const toPlayerId = assignments[toSlotId];

    delete next[fromSlotId];
    delete next[toSlotId];

    if (toPlayerId !== undefined) {
        next[fromSlotId] = toPlayerId;
    }
    if (fromPlayerId !== undefined) {
        next[toSlotId] = fromPlayerId;
    }

    return next;
}

/** Drag payload MIME type used when dragging a player row onto another row. */
export const LINEUP_SLOT_DRAG_TYPE = 'application/x-lineup-slot';

/** Places the starting eleven onto their formation slots, falling back to the slot's position label. */
export function pitchPlayersForFormation(
    formation: Formation | undefined,
    assignments: Record<string, number>,
    players: SquadPlayer[],
): PitchPlayer[] {
    const playersById = new Map(players.map((player) => [player.id, player]));

    return (
        formation?.slots.map((slot, index) => {
            const player = playersById.get(assignments[slot.slot]);

            return {
                key: slot.slot,
                number: index + 1,
                label: player?.last_name ?? slot.position,
                x: slot.x,
                y: slot.y,
                hasArrow: slot.has_arrow,
            };
        }) ?? []
    );
}

/** The editable instructions of a saved tactic, as the Team Instructions modal starts them. */
export function draftFromTactic(tactic: Tactic): TacticsDraft {
    return {
        formationId: tactic.formation.id,
        mentality: tactic.mentality,
        pressing: tactic.pressing,
        passing: tactic.passing,
        tackling: tactic.tackling,
        offside_trap: tactic.offside_trap,
        counter_attack: tactic.counter_attack,
        men_behind_ball: tactic.men_behind_ball,
        free_kicks_left_player_id: tactic.free_kicks_left_player_id,
        free_kicks_right_player_id: tactic.free_kicks_right_player_id,
        corners_left_player_id: tactic.corners_left_player_id,
        corners_right_player_id: tactic.corners_right_player_id,
        playmaker_player_id: tactic.playmaker_player_id,
    };
}
