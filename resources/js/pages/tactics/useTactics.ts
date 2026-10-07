import { useEffect, useState } from 'react';
import {
    show as showLineup,
    store as storeLineup,
} from '@/actions/App/Http/Controllers/SquadLineupController';
import {
    show as showTactics,
    update as updateTactics,
} from '@/actions/App/Http/Controllers/TacticsController';
import api from '@/api';
import type { LineupAssignments } from '../squad/types';
import type { Tactic, TacticsData, TacticsDraft } from './types';
import {
    extractErrorMessage,
    lineupPositionForSlot,
    swapSlotAssignments,
} from './utils';

type SavedSlot = {
    slot: string;
    position: string;
    player_id: number;
};

/**
 * Loads the club's tactic and saved lineup. Team instructions are saved
 * straight to /api/tactics by saveTactics(); the page keeps no unsaved copy.
 */
export function useTactics() {
    const [tactics, setTactics] = useState<TacticsData | null>(null);
    const [assignments, setAssignments] = useState<LineupAssignments>({});
    const [loadError, setLoadError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [lineupError, setLineupError] = useState<string | null>(null);

    useEffect(() => {
        api.get(showTactics.url())
            .then((response) => {
                setTactics(response.data.data as TacticsData);
            })
            .catch(() => setLoadError('Unable to load tactics.'));

        api.get(showLineup.url()).then((response) => {
            const slots = response.data.data as SavedSlot[];
            setAssignments(
                Object.fromEntries(
                    slots.map((slot) => [slot.slot, slot.player_id]),
                ),
            );
        });
    }, []);

    function clearSaveError() {
        setSaveError(null);
    }

    /** Persists the given instructions; resolves to whether the save succeeded. */
    function saveTactics(draft: TacticsDraft): Promise<boolean> {
        setIsSaving(true);
        setSaveError(null);

        const { formationId, ...instructions } = draft;

        return api
            .put(updateTactics.url(), {
                formation_id: formationId,
                ...instructions,
            })
            .then((response) => {
                const tactic = response.data.data as Tactic;
                setTactics((current) =>
                    current ? { ...current, tactic } : current,
                );

                return true;
            })
            .catch((error) => {
                setSaveError(extractErrorMessage(error));

                return false;
            })
            .finally(() => setIsSaving(false));
    }

    /**
     * Exchanges the players in two lineup slots and saves the whole lineup,
     * restoring the previous lineup if the save fails.
     */
    function swapLineupSlots(fromSlotId: string, toSlotId: string) {
        if (fromSlotId === toSlotId || !tactics) {
            return;
        }

        const previousAssignments = assignments;
        const nextAssignments = swapSlotAssignments(
            assignments,
            fromSlotId,
            toSlotId,
        );
        setAssignments(nextAssignments);
        setLineupError(null);

        api.post(storeLineup.url(), {
            assignments: Object.entries(nextAssignments).map(
                ([slot, playerId]) => ({
                    slot,
                    player_id: playerId,
                    position: lineupPositionForSlot(
                        slot,
                        tactics.tactic.formation,
                    ),
                }),
            ),
        }).catch((error) => {
            setAssignments(previousAssignments);
            setLineupError(extractErrorMessage(error));
        });
    }

    return {
        tactics,
        assignments,
        swapLineupSlots,
        lineupError,
        loadError,
        saveTactics,
        isSaving,
        saveError,
        clearSaveError,
    };
}
