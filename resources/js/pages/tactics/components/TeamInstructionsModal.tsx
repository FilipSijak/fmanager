import { useState } from 'react';
import type { SquadPlayer } from '../../squad/types';
import type { TacticsData, TacticsDraft } from '../types';
import { draftFromTactic, formatOptionLabel } from '../utils';

type ChoiceRow = {
    kind: 'choice';
    label: string;
    options: { value: string | number | boolean; label: string }[];
    value: string | number | boolean;
    onChange: (value: string | number | boolean) => void;
};

type PlayerRow = {
    kind: 'player';
    label: string;
    value: number | null;
    onChange: (playerId: number | null) => void;
};

type PlayerRoleField =
    | 'free_kicks_left_player_id'
    | 'free_kicks_right_player_id'
    | 'corners_left_player_id'
    | 'corners_right_player_id'
    | 'playmaker_player_id';

type ToggleField = 'offside_trap' | 'counter_attack' | 'men_behind_ball';

const YES_NO_OPTIONS = [
    { value: false, label: 'No' },
    { value: true, label: 'Yes' },
];

const TOGGLE_ROWS: { field: ToggleField; label: string }[] = [
    { field: 'offside_trap', label: 'Offside Trap' },
    { field: 'counter_attack', label: 'Counter Attack' },
    { field: 'men_behind_ball', label: 'Men Behind Ball' },
];

const PLAYER_ROLE_ROWS: { field: PlayerRoleField; label: string }[] = [
    { field: 'free_kicks_left_player_id', label: 'Free Kicks (L)' },
    { field: 'free_kicks_right_player_id', label: 'Free Kicks (R)' },
    { field: 'corners_left_player_id', label: 'Corners (L)' },
    { field: 'corners_right_player_id', label: 'Corners (R)' },
    { field: 'playmaker_player_id', label: 'Playmaker' },
];

/**
 * Edits a copy of the saved tactic. Ok saves it and closes only once the
 * save succeeds; Cancel discards it.
 */
export default function TeamInstructionsModal({
    tactics,
    players,
    isSaving,
    saveError,
    onSave,
    onClose,
}: {
    tactics: TacticsData;
    /** Players offered for set pieces and playmaker. */
    players: SquadPlayer[];
    isSaving: boolean;
    saveError: string | null;
    onSave: (instructions: TacticsDraft) => Promise<boolean>;
    onClose: () => void;
}) {
    const [instructions, setInstructions] = useState<TacticsDraft>(() =>
        draftFromTactic(tactics.tactic),
    );

    function save() {
        onSave(instructions).then((isSaved) => {
            if (isSaved) {
                onClose();
            }
        });
    }

    function updateInstructions(changes: Partial<TacticsDraft>) {
        setInstructions((current) => ({ ...current, ...changes }));
    }

    function choiceRow(
        label: string,
        values: string[],
        field: 'mentality' | 'passing' | 'tackling' | 'pressing',
    ): ChoiceRow {
        return {
            kind: 'choice',
            label,
            options: values.map((value) => ({
                value,
                label: formatOptionLabel(value),
            })),
            value: instructions[field],
            onChange: (value) => updateInstructions({ [field]: String(value) }),
        };
    }

    const rows: (ChoiceRow | PlayerRow)[] = [
        {
            kind: 'choice',
            label: 'Formation',
            options: tactics.formations.map((formation) => ({
                value: formation.id,
                label: formation.name,
            })),
            value: instructions.formationId,
            onChange: (value) =>
                updateInstructions({ formationId: Number(value) }),
        },
        choiceRow('Mentality', tactics.options.mentalities, 'mentality'),
        choiceRow('Passing', tactics.options.passing, 'passing'),
        choiceRow('Tackling', tactics.options.tackling, 'tackling'),
        choiceRow('Pressing', tactics.options.pressing, 'pressing'),
        ...TOGGLE_ROWS.map(({ field, label }): ChoiceRow => ({
            kind: 'choice',
            label,
            options: YES_NO_OPTIONS,
            value: instructions[field],
            onChange: (value) =>
                updateInstructions({ [field]: value === true }),
        })),
        ...PLAYER_ROLE_ROWS.map(({ field, label }): PlayerRow => ({
            kind: 'player',
            label,
            value: instructions[field],
            onChange: (playerId) => updateInstructions({ [field]: playerId }),
        })),
    ];

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="team-instructions-title"
                className="flex max-h-full w-full max-w-2xl flex-col border-4 border-emerald-500 bg-emerald-600 p-3 shadow-2xl"
            >
                <div className="border-t-2 border-l-2 border-blue-600 bg-[#0c0c14] py-3">
                    <h2
                        id="team-instructions-title"
                        className="text-center text-xl font-bold text-white"
                    >
                        Team Instructions
                    </h2>
                </div>

                <div className="mt-4 min-h-0 overflow-y-auto border-2 border-emerald-300/60 bg-gradient-to-b from-emerald-500 to-emerald-600 p-2">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="grid grid-cols-[minmax(0,180px)_1fr] items-center gap-2 py-0.5 odd:bg-white/5"
                        >
                            <span className="pl-2 text-lg font-bold text-white">
                                {row.label}
                            </span>
                            {row.kind === 'choice' ? (
                                <div className="grid grid-cols-4 gap-1">
                                    {row.options.map((option) => {
                                        const isSelected =
                                            option.value === row.value;

                                        return (
                                            <button
                                                key={String(option.value)}
                                                type="button"
                                                aria-pressed={isSelected}
                                                onClick={() =>
                                                    row.onChange(option.value)
                                                }
                                                className={`border-2 px-2 py-1 text-sm font-bold ${
                                                    isSelected
                                                        ? 'border-[#f5f000] bg-emerald-600 text-[#f5f000]'
                                                        : 'border-transparent text-lime-200 hover:border-lime-200/40'
                                                }`}
                                            >
                                                {option.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                <select
                                    aria-label={row.label}
                                    value={row.value ?? ''}
                                    onChange={(event) =>
                                        row.onChange(
                                            event.target.value === ''
                                                ? null
                                                : Number(event.target.value),
                                        )
                                    }
                                    className="w-56 cursor-pointer border-2 border-transparent bg-transparent px-2 py-1 text-sm font-bold text-lime-200 hover:border-lime-200/40 focus:border-[#f5f000] focus:outline-none"
                                >
                                    <option
                                        value=""
                                        className="bg-[#0c0c14] text-white"
                                    >
                                        —
                                    </option>
                                    {players.map((player) => (
                                        <option
                                            key={player.id}
                                            value={player.id}
                                            className="bg-[#0c0c14] text-white"
                                        >
                                            {player.first_name.charAt(0)}.{' '}
                                            {player.last_name}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>
                    ))}
                </div>

                {saveError && (
                    <p className="mt-3 text-center text-sm font-bold text-red-200">
                        {saveError}
                    </p>
                )}

                <div className="mt-4 flex gap-1">
                    <button
                        type="button"
                        disabled={isSaving}
                        onClick={onClose}
                        className="flex-1 bg-slate-200 py-2 text-lg font-bold text-slate-500 hover:bg-white disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={isSaving}
                        onClick={save}
                        className="flex-1 bg-slate-200 py-2 text-lg font-bold text-slate-500 hover:bg-white disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isSaving ? 'Saving...' : 'Ok'}
                    </button>
                </div>
            </div>
        </div>
    );
}
