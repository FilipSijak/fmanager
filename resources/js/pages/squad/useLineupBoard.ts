import { useEffect, useState } from 'react';
import {
    show as showLineup,
    store as storeLineup,
} from '@/actions/App/Http/Controllers/SquadLineupController';
import { show as showTactics } from '@/actions/App/Http/Controllers/TacticsController';
import api from '@/api';
import type { Formation, TacticsData } from '../tactics/types';
import type { LineupAssignments } from './types';
import { extractErrorMessage, positionChipsForFormation } from './utils';

type SavedSlot = {
    slot: string;
    position: string;
    player_id: number;
};

/**
 * Tracks which position slot of the club's current formation (or bench slot)
 * each player is assigned to, loaded from and saved to /api/squad/lineup.
 */
export function useLineupBoard() {
    const [formation, setFormation] = useState<Formation | null>(null);
    const [assignments, setAssignments] = useState<LineupAssignments>({});
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    const positionChips = positionChipsForFormation(formation);

    useEffect(() => {
        api.get(showTactics.url()).then((response) => {
            setFormation((response.data.data as TacticsData).tactic.formation);
        });

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

    /** The position label of the chip a player holds, e.g. "CB" or "SUB". */
    function chipLabelForPlayer(playerId: number): string | null {
        const chipId = chipIdForPlayer(playerId);

        return positionChips.find((chip) => chip.id === chipId)?.label ?? null;
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
        if (formation === null) {
            return;
        }

        if (chipIdForPlayer(playerId) !== null) {
            unassignPlayer(playerId);
            return;
        }

        const nextChip = positionChips.find(
            (chip) => assignments[chip.id] === undefined,
        );

        if (nextChip) {
            assignChipToPlayer(nextChip.id, playerId);
        }
    }

    function saveLineup() {
        setIsSaving(true);
        setSaveError(null);

        const assignmentsPayload = Object.entries(assignments).map(
            ([slot, playerId]) => ({
                slot,
                player_id: playerId,
                position:
                    positionChips.find((chip) => chip.id === slot)?.label ??
                    slot,
            }),
        );

        return api
            .post(storeLineup.url(), { assignments: assignmentsPayload })
            .catch((error) => setSaveError(extractErrorMessage(error)))
            .finally(() => setIsSaving(false));
    }

    /** Deselects every player and saves the now-empty lineup. */
    function clearLineup() {
        setAssignments({});
        setIsSaving(true);
        setSaveError(null);

        return api
            .post(storeLineup.url(), { assignments: [] })
            .catch((error) => setSaveError(extractErrorMessage(error)))
            .finally(() => setIsSaving(false));
    }

    return {
        positionChips,
        chipLabelForPlayer,
        playerIdForChip,
        assignChipToPlayer,
        togglePlayerBox,
        saveLineup,
        clearLineup,
        isSaving,
        saveError,
    };
}
