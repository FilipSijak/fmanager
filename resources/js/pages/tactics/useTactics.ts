import { useEffect, useState } from 'react';
import { show as showLineup } from '@/actions/App/Http/Controllers/SquadLineupController';
import {
    show as showTactics,
    update as updateTactics,
} from '@/actions/App/Http/Controllers/TacticsController';
import api from '@/api';
import type { LineupAssignments } from '../squad/types';
import type { Tactic, TacticsData, TacticsDraft } from './types';
import { extractErrorMessage } from './utils';

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

    return {
        tactics,
        assignments,
        loadError,
        saveTactics,
        isSaving,
        saveError,
        clearSaveError,
    };
}
