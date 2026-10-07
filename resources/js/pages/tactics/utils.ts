import type { SquadPlayer } from '../squad/types';
import { POSITION_CHIPS } from '../squad/utils';
import type { Formation, LineupPlayer, PitchPlayer } from './types';

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

/** Resolves each slot id to its assigned squad player, numbering them from firstNumber. */
export function lineupPlayersForSlots(
    slotIds: string[],
    assignments: Record<string, number>,
    players: SquadPlayer[],
    firstNumber: number,
): LineupPlayer[] {
    const playersById = new Map(players.map((player) => [player.id, player]));

    return slotIds.flatMap((slotId, index) => {
        const player = playersById.get(assignments[slotId]);

        return player ? [{ number: firstNumber + index, player }] : [];
    });
}

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
