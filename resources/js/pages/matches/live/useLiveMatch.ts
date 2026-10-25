import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { nextDay } from '@/actions/App/Http/Controllers/InstanceController';
import {
    advance,
    substitute as substituteRoute,
} from '@/actions/App/Http/Controllers/LiveMatchController';
import api from '@/api';
import { start } from '@/routes';
import { extractErrorMessage } from '../../squad/utils';
import type { LiveMatchData } from './types';

/** Real-time gap between clock ticks. */
const TICK_MS = 1000;

/** Game minutes played per tick at each speed. */
export const SPEEDS = [
    { label: '1×', minutesPerTick: 1 },
    { label: '3×', minutesPerTick: 3 },
    { label: '10×', minutesPerTick: 10 },
] as const;

/**
 * Runs the match clock: while playing, asks the server to play the next few minutes every tick. Substitutions can
 * only be made while the clock is paused.
 */
export function useLiveMatch(initialMatch: LiveMatchData) {
    const [match, setMatch] = useState(initialMatch);
    const [playing, setPlaying] = useState(false);
    const [speed, setSpeed] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!playing || match.finished) {
            return;
        }

        const timer = window.setTimeout(() => {
            api.post(advance.url(match.game_id), {
                minutes: SPEEDS[speed].minutesPerTick,
            })
                .then((response) => {
                    setMatch(response.data.data);

                    if (response.data.data.finished) {
                        setPlaying(false);
                    }
                })
                .catch((requestError) => {
                    setPlaying(false);
                    setError(extractErrorMessage(requestError));
                });
        }, TICK_MS);

        return () => window.clearTimeout(timer);
    }, [playing, match, speed]);

    function togglePlaying() {
        setError(null);
        setPlaying((current) => !current && !match.finished);
    }

    async function substitute(playerOffId: number, playerOnId: number) {
        setBusy(true);
        setError(null);

        try {
            const response = await api.post(
                substituteRoute.url(match.game_id),
                { player_off_id: playerOffId, player_on_id: playerOnId },
            );
            setMatch(response.data.data);

            return true;
        } catch (requestError) {
            setError(extractErrorMessage(requestError));

            return false;
        } finally {
            setBusy(false);
        }
    }

    /** Finishes the match day: the rest of today's games are played and the game moves on to tomorrow. */
    async function continueAfterMatch() {
        setBusy(true);
        setError(null);

        try {
            await api.post(nextDay.url());
            router.visit(start.url());
        } catch (requestError) {
            setError(extractErrorMessage(requestError));
            setBusy(false);
        }
    }

    return {
        match,
        playing,
        speed,
        setSpeed,
        togglePlaying,
        substitute,
        continueAfterMatch,
        error,
        busy,
    };
}
