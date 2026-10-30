import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { type FormEvent, useState } from 'react';
import type {
    Category,
    Intensity,
    Preset,
    TrainingPlayer,
    TrainingSchedule,
} from '../types';
import { buttonClass, fieldClass, sameSchedule } from '../utils';

export default function PlayerTrainingDialog({
    player,
    categories,
    intensities,
    presets,
    saving,
    error,
    onSave,
    onClose,
}: {
    player: TrainingPlayer;
    categories: Category[];
    intensities: Intensity[];
    presets: Preset[];
    saving: boolean;
    error: string | null;
    onSave: (
        playerIds: number[],
        schedule: TrainingSchedule,
    ) => Promise<boolean>;
    onClose: () => void;
}) {
    const [schedule, setSchedule] = useState<TrainingSchedule>({
        ...player.schedule,
    });
    const availablePresets = presets.filter(
        (preset) => !preset.goalkeeper_only || player.position === 'GK',
    );
    const selectedPreset =
        availablePresets.find((preset) =>
            sameSchedule(schedule, preset.schedule, player.position === 'GK'),
        )?.id ?? 'custom';

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (await onSave([player.id], schedule)) onClose();
    }

    return (
        <Dialog
            open
            onClose={() => {
                if (!saving) onClose();
            }}
            className="relative z-[60]"
            style={{ fontFamily: "'Courier New', monospace" }}
        >
            <div className="fixed inset-0 bg-black/80" aria-hidden="true" />
            <div className="fixed inset-0 overflow-y-auto">
                <div className="flex min-h-full items-center justify-center p-5">
                    <DialogPanel className="w-full max-w-xl border-2 border-[#3355dd] bg-[#10102c] p-6 text-white shadow-2xl">
                        <DialogTitle className="text-2xl font-bold text-[#f5f000]">
                            {player.name}
                        </DialogTitle>
                        <p className="mt-2 text-sm text-cyan-300">
                            {player.position} · Individual training
                        </p>
                        <form
                            onSubmit={submit}
                            className="mt-6 flex flex-col gap-5"
                        >
                            <fieldset
                                disabled={saving}
                                className="flex flex-col gap-5"
                            >
                                <label className="flex flex-col gap-2 text-sm">
                                    Quick plan
                                    <select
                                        value={selectedPreset}
                                        onChange={(event) => {
                                            const preset = presets.find(
                                                (item) =>
                                                    item.id ===
                                                    event.target.value,
                                            );
                                            if (preset)
                                                setSchedule({
                                                    ...preset.schedule,
                                                });
                                        }}
                                        className={fieldClass}
                                    >
                                        <option value="custom" disabled>
                                            Custom schedule
                                        </option>
                                        {availablePresets.map((preset) => (
                                            <option
                                                key={preset.id}
                                                value={preset.id}
                                            >
                                                {preset.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {categories
                                        .filter(
                                            (category) =>
                                                category.key !==
                                                    'goalkeeping' ||
                                                player.position === 'GK',
                                        )
                                        .map((category) => (
                                            <label
                                                key={category.id}
                                                className="flex flex-col gap-2 text-sm"
                                            >
                                                {category.name}
                                                <select
                                                    value={
                                                        schedule[category.key]
                                                    }
                                                    onChange={(event) =>
                                                        setSchedule(
                                                            (current) => ({
                                                                ...current,
                                                                [category.key]:
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                            }),
                                                        )
                                                    }
                                                    className={fieldClass}
                                                >
                                                    {intensities.map(
                                                        (intensity) => (
                                                            <option
                                                                key={
                                                                    intensity.id
                                                                }
                                                                value={
                                                                    intensity.id
                                                                }
                                                            >
                                                                {intensity.name ===
                                                                'None'
                                                                    ? 'None — no training'
                                                                    : intensity.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                        ))}
                                </div>
                            </fieldset>
                            <div className="border-l-2 border-cyan-300/50 pl-4 text-sm leading-relaxed text-slate-300">
                                <p>
                                    Medium develops attributes. Hard adds
                                    progress and can reduce condition when
                                    combined with other categories. Light
                                    maintains progress.
                                </p>
                                <p className="mt-3">
                                    Full rest restores condition and preserves
                                    progress. Rest stays active until you choose
                                    another plan. Turning off a single category
                                    gradually reduces its progress.
                                </p>
                            </div>
                            {error && (
                                <p
                                    role="alert"
                                    className="text-sm text-red-400"
                                >
                                    {error}
                                </p>
                            )}
                            <div className="flex justify-end gap-3 border-t border-[#3355dd] pt-4">
                                <button
                                    type="button"
                                    disabled={saving}
                                    onClick={onClose}
                                    className={buttonClass}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className={buttonClass}
                                >
                                    {saving ? 'Saving…' : 'Save plan'}
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}
