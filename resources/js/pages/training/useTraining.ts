import { router } from '@inertiajs/react';
import axios from 'axios';
import { useRef, useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/Api/TrainingController';
import api from '@/api';
import type { TrainingSchedule } from './types';

export function useTraining() {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const pending = useRef(false);

    async function save(
        playerIds: number[],
        schedule: TrainingSchedule,
    ): Promise<boolean> {
        if (pending.current || playerIds.length === 0) return false;
        pending.current = true;
        setSaving(true);
        setError(null);
        setNotice(null);
        try {
            await api.put(update.url(), { player_ids: playerIds, schedule });
            await new Promise<void>((resolve) =>
                router.reload({ only: ['players'], onFinish: () => resolve() }),
            );
            setNotice(
                `Training saved for ${playerIds.length === 1 ? '1 player' : `${playerIds.length} players`}. Changes apply to the next training session.`,
            );
            return true;
        } catch (failure) {
            if (axios.isAxiosError(failure)) {
                const validation = failure.response?.data.errors as
                    | Record<string, string[]>
                    | undefined;
                setError(
                    validation
                        ? Object.values(validation).flat().join(' ')
                        : failure.response?.status === 404
                          ? 'One or more selected players are no longer in your squad. Refresh the page and try again.'
                          : 'Unable to save training. Please try again.',
                );
            } else {
                setError('Unable to save training. Please try again.');
            }
            return false;
        } finally {
            pending.current = false;
            setSaving(false);
        }
    }

    return {
        save,
        saving,
        error,
        notice,
        clearMessages: () => {
            setError(null);
            setNotice(null);
        },
    };
}
