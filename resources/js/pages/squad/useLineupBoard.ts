import { useState } from 'react';
import type { LineupAssignments } from './types';
import { POSITION_CHIPS } from './utils';

/**
 * Tracks which position slot (from POSITION_CHIPS) each player is assigned
 * to. Purely client-side UI state for building a lineup on the squad
 * screen - not persisted anywhere.
 */
export function useLineupBoard() {
    const [assignments, setAssignments] = useState<LineupAssignments>({});

    function chipIdForPlayer(playerId: number): string | null {
        return (
            Object.keys(assignments).find(
                (chipId) => assignments[chipId] === playerId,
            ) ?? null
        );
    }

    function playerIdForChip(chipId: string): number | undefined {
        return assignments[chipId];
    }

    function unassignPlayer(playerId: number) {
        setAssignments((current) => {
            const next = { ...current };
            for (const chipId of Object.keys(next)) {
                if (next[chipId] === playerId) {
                    delete next[chipId];
                }
            }
            return next;
        });
    }

    /** Assigns a specific chip to a player, freeing both the chip's previous holder and the player's previous chip. */
    function assignChipToPlayer(chipId: string, playerId: number) {
        setAssignments((current) => {
            const next = { ...current };
            for (const existingChipId of Object.keys(next)) {
                if (next[existingChipId] === playerId) {
                    delete next[existingChipId];
                }
            }
            next[chipId] = playerId;
            return next;
        });
    }

    /** Click handler for a player's box: assigns the next free slot, or clears their current one. */
    function togglePlayerBox(playerId: number) {
        if (chipIdForPlayer(playerId) !== null) {
            unassignPlayer(playerId);
            return;
        }

        const nextChip = POSITION_CHIPS.find(
            (chip) => assignments[chip.id] === undefined,
        );

        if (nextChip) {
            assignChipToPlayer(nextChip.id, playerId);
        }
    }

    return {
        chipIdForPlayer,
        playerIdForChip,
        assignChipToPlayer,
        togglePlayerBox,
    };
}
