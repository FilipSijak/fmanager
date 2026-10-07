import { useState } from 'react';
import type { TacticsData, TacticsDraft } from '../types';
import { formatOptionLabel } from '../utils';

type InstructionRow = {
    label: string;
    options: { value: string | number; label: string }[];
    value: string | number;
    onChange: (value: string | number) => void;
};

/**
 * Edits a local copy of the tactic draft; Ok hands the copy back via onApply,
 * Cancel throws it away.
 */
export default function TeamInstructionsModal({
    tactics,
    draft,
    onApply,
    onClose,
}: {
    tactics: TacticsData;
    draft: TacticsDraft;
    onApply: (draft: TacticsDraft) => void;
    onClose: () => void;
}) {
    const [instructions, setInstructions] = useState<TacticsDraft>(draft);

    function updateInstructions(changes: Partial<TacticsDraft>) {
        setInstructions((current) => ({ ...current, ...changes }));
    }

    function toOptions(values: string[]) {
        return values.map((value) => ({
            value,
            label: formatOptionLabel(value),
        }));
    }

    const rows: InstructionRow[] = [
        {
            label: 'Formation',
            options: tactics.formations.map((formation) => ({
                value: formation.id,
                label: formation.name,
            })),
            value: instructions.formationId,
            onChange: (value) =>
                updateInstructions({ formationId: Number(value) }),
        },
        {
            label: 'Mentality',
            options: toOptions(tactics.options.mentalities),
            value: instructions.mentality,
            onChange: (value) =>
                updateInstructions({ mentality: String(value) }),
        },
        {
            label: 'Passing',
            options: toOptions(tactics.options.passing),
            value: instructions.passing,
            onChange: (value) => updateInstructions({ passing: String(value) }),
        },
        {
            label: 'Pressing',
            options: toOptions(tactics.options.pressing),
            value: instructions.pressing,
            onChange: (value) =>
                updateInstructions({ pressing: String(value) }),
        },
    ];

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="team-instructions-title"
                className="w-full max-w-2xl border-4 border-emerald-500 bg-emerald-600 p-3 shadow-2xl"
            >
                <div className="border-t-2 border-l-2 border-blue-600 bg-[#0c0c14] py-3">
                    <h2
                        id="team-instructions-title"
                        className="text-center text-xl font-bold text-white"
                    >
                        Team Instructions
                    </h2>
                </div>

                <div className="mt-4 border-2 border-emerald-300/60 bg-gradient-to-b from-emerald-500 to-emerald-600 p-2">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="grid grid-cols-[minmax(0,180px)_1fr] items-center gap-2 py-0.5 odd:bg-white/5"
                        >
                            <span className="pl-2 text-lg font-bold text-white">
                                {row.label}
                            </span>
                            <div className="grid grid-cols-4 gap-1">
                                {row.options.map((option) => {
                                    const isSelected =
                                        option.value === row.value;

                                    return (
                                        <button
                                            key={option.value}
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
                        </div>
                    ))}
                </div>

                <div className="mt-4 flex gap-1">
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex-1 bg-slate-200 py-2 text-lg font-bold text-slate-500 hover:bg-white"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            onApply(instructions);
                            onClose();
                        }}
                        className="flex-1 bg-slate-200 py-2 text-lg font-bold text-slate-500 hover:bg-white"
                    >
                        Ok
                    </button>
                </div>
            </div>
        </div>
    );
}
