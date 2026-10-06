import { useEffect, useState } from 'react';
import {
    show as showLineup,
    store as storeLineup,
} from '@/actions/App/Http/Controllers/SquadLineupController';
import api from '@/api';
import type { LineupAssignments, SquadPlayer } from './types';
import { extractErrorMessage, POSITION_CHIPS } from './utils';

type SavedSlot = {
    slot: string;
    position: string;
    player_id: number;
};

/**
 * Tracks which position slot (from POSITION_CHIPS) each player is assigned
 * to, loaded from and saved to /api/squad/lineup.
 */
export function useLineupBoard() {
    const [assignments, setAssignments] = useState<LineupAssignments>({});
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    useEffect(() => {
        api.get(showLineup.url()).then((response) => {
            const slots = response.data.data as SavedSlot[];
            setAssignments(
                Object.fromEntries(
                    slots.map((slot) => [slot.slot, slot.player_id]),
                ),
            );
        });
    }, []);

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

    function saveLineup(players: SquadPlayer[]) {
        setIsSaving(true);
        setSaveError(null);

        const playersById = new Map(
            players.map((player) => [player.id, player]),
        );
        const assignmentsPayload = Object.entries(assignments)
            .map(([slot, playerId]) => {
                const player = playersById.get(playerId);
                return player
                    ? { slot, player_id: playerId, position: player.position }
                    : null;
            })
            .filter(
                (
                    entry,
                ): entry is {
                    slot: string;
                    player_id: number;
                    position: string;
                } => entry !== null,
            );

        return api
            .post(storeLineup.url(), { assignments: assignmentsPayload })
            .catch((error) => setSaveError(extractErrorMessage(error)))
            .finally(() => setIsSaving(false));
    }

    return {
        chipIdForPlayer,
        playerIdForChip,
        assignChipToPlayer,
        togglePlayerBox,
        saveLineup,
        isSaving,
        saveError,
    };
}
