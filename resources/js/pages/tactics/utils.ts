import type { SquadPlayer } from '../squad/types';
import { POSITION_CHIPS } from '../squad/utils';
import type {
    Formation,
    LineupPlayer,
    PitchPlayer,
    Tactic,
    TacticsDraft,
} from './types';

export { extractErrorMessage } from '../squad/utils';

const STARTER_COUNT = 11;

/** Lineup slot ids saved by the Squad page, in formation order (GK first). */
export const STARTER_SLOT_IDS = POSITION_CHIPS.slice(0, STARTER_COUNT).map(
    (chip) => chip.id,
);

export const SUBSTITUTE_SLOT_IDS = POSITION_CHIPS.slice(STARTER_COUNT).map(
    (chip) => chip.id,
);

export function formatOptionLabel(option: string): string {
    return option.charAt(0).toUpperCase() + option.slice(1);
}

export const SUBSTITUTE_POSITION_LABEL = 'SUB';

/**
 * The position each lineup slot is picked for: starters take the formation
 * slot at the same index, substitutes are labelled SUB.
 */
export function lineupPositionForSlot(
    slotId: string,
    formation: Formation | undefined,
): string {
    const starterIndex = STARTER_SLOT_IDS.indexOf(slotId);

    if (starterIndex === -1) {
        return SUBSTITUTE_POSITION_LABEL;
    }

    return formation?.slots[starterIndex]?.position ?? slotId;
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

/** Places the starting eleven onto the formation's slots by order, falling back to the slot's position label. */
export function pitchPlayersForFormation(
    formation: Formation | undefined,
    assignments: Record<string, number>,
    players: SquadPlayer[],
): PitchPlayer[] {
    const playersById = new Map(players.map((player) => [player.id, player]));

    return (
        formation?.slots.map((slot, index) => {
            const player = playersById.get(
                assignments[STARTER_SLOT_IDS[index]],
            );

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
