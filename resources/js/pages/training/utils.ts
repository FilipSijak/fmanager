import type { Preset, TrainingPlayer, TrainingSchedule } from './types';

export const buttonClass =
    'border border-[#3355dd] px-4 py-2 text-sm font-bold text-[#f5f000] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-[#f5f000] disabled:cursor-not-allowed disabled:opacity-40';
export const fieldClass =
    'w-full border border-[#3355dd] bg-[#080820] px-3 py-2 text-sm text-white focus:outline-2 focus:outline-[#f5f000] disabled:opacity-40';

export function sameSchedule(
    left: TrainingSchedule,
    right: TrainingSchedule,
    goalkeeper: boolean,
) {
    return (
        left.physical === right.physical &&
        left.tactical === right.tactical &&
        left.technical === right.technical &&
        (!goalkeeper || left.goalkeeping === right.goalkeeping)
    );
}

export function planName(player: TrainingPlayer, presets: Preset[]) {
    return (
        presets.find(
            (preset) =>
                (!preset.goalkeeper_only || player.position === 'GK') &&
                sameSchedule(
                    player.schedule,
                    preset.schedule,
                    player.position === 'GK',
                ),
        )?.name ?? 'Custom'
    );
}

export function conditionColor(condition: number) {
    return condition < 70
        ? 'text-red-400'
        : condition < 90
          ? 'text-amber-300'
          : 'text-[#39ff14]';
}
