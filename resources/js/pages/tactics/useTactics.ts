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

function draftFromTactic(tactic: Tactic): TacticsDraft {
    return {
        formationId: tactic.formation.id,
        mentality: tactic.mentality,
        pressing: tactic.pressing,
        passing: tactic.passing,
    };
}

/**
 * Loads the club's tactic and saved lineup, and keeps an editable draft of
 * the tactic that is only persisted to /api/tactics when saveTactics() runs.
 */
export function useTactics() {
    const [tactics, setTactics] = useState<TacticsData | null>(null);
    const [draft, setDraft] = useState<TacticsDraft | null>(null);
    const [assignments, setAssignments] = useState<LineupAssignments>({});
    const [loadError, setLoadError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    useEffect(() => {
        api.get(showTactics.url())
            .then((response) => {
                const data = response.data.data as TacticsData;
                setTactics(data);
                setDraft(draftFromTactic(data.tactic));
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

    const selectedFormation =
        tactics?.formations.find(
            (formation) => formation.id === draft?.formationId,
        ) ?? tactics?.tactic.formation;

    const hasChanges =
        tactics !== null &&
        draft !== null &&
        JSON.stringify(draft) !==
            JSON.stringify(draftFromTactic(tactics.tactic));

    function updateDraft(changes: Partial<TacticsDraft>) {
        setDraft((current) => (current ? { ...current, ...changes } : current));
    }

    /** Discards unsaved changes, reverting the draft to the last saved tactic. */
    function resetDraft() {
        if (tactics) {
            setDraft(draftFromTactic(tactics.tactic));
        }
        setSaveError(null);
    }

    function saveTactics() {
        if (!draft) {
            return;
        }

        setIsSaving(true);
        setSaveError(null);

        return api
            .put(updateTactics.url(), {
                formation_id: draft.formationId,
                mentality: draft.mentality,
                pressing: draft.pressing,
                passing: draft.passing,
            })
            .then((response) => {
                const tactic = response.data.data as Tactic;
                setTactics((current) =>
                    current ? { ...current, tactic } : current,
                );
                setDraft(draftFromTactic(tactic));
            })
            .catch((error) => setSaveError(extractErrorMessage(error)))
            .finally(() => setIsSaving(false));
    }

    return {
        tactics,
        draft,
        selectedFormation,
        assignments,
        loadError,
        hasChanges,
        updateDraft,
        resetDraft,
        saveTactics,
        isSaving,
        saveError,
    };
}
