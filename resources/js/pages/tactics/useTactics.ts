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
