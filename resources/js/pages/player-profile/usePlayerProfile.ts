import { useEffect, useState } from 'react';
import { index as showDashboard } from '@/actions/App/Http/Controllers/Api/DashboardController';
import { show as showPlayer } from '@/actions/App/Http/Controllers/Api/PlayerController';
import api from '@/api';
import type { PlayerProfileData } from './types';

export function usePlayerProfile(playerId: number) {
    const [player, setPlayer] = useState<PlayerProfileData | null>(null);
    const [instanceDate, setInstanceDate] = useState<string | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [managedClubId, setManagedClubId] = useState<number | null>(null);

    const [selection, setSelection] = useState<{
        playerId: number;
        seasonId: number;
    } | null>(null);
    const [loadingPerformance, setLoadingPerformance] = useState(false);
    const [seasonError, setSeasonError] = useState<string | null>(null);
    useEffect(() => {
        const seasonId =
            selection?.playerId === playerId ? selection.seasonId : null;
        const controller = new AbortController();
        setPlayer((previous) => (previous?.id === playerId ? previous : null));
        setLoadingPerformance(true);
        setSeasonError(null);
        setLoadError(null);

        Promise.all([
            api.get(showDashboard.url(), { signal: controller.signal }),
            api.get(
                showPlayer.url(playerId, {
                    query: seasonId === null ? {} : { season_id: seasonId },
                }),
                { signal: controller.signal },
            ),
        ])
            .then(([dashboardResponse, playerResponse]) => {
                if (controller.signal.aborted) return;
                const instance = dashboardResponse.data.data.instance as {
                    date: string;
                };
                setInstanceDate(instance.date);
                setManagedClubId(dashboardResponse.data.data.club?.id ?? null);
                setPlayer(playerResponse.data.data as PlayerProfileData);
            })
            .catch(() => {
                if (controller.signal.aborted) return;
                if (seasonId === null) {
                    setLoadError('Unable to load this player.');
                } else {
                    setSeasonError(
                        'Unable to load that season. Please try again.',
                    );
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoadingPerformance(false);
            });

        return () => controller.abort();
    }, [playerId, selection]);

    return {
        player: player?.id === playerId ? player : null,
        instanceDate,
        loadError,
        managedClubId,
        loadingPerformance,
        seasonError,
        selectSeason: (selectedSeasonId: number) =>
            setSelection({ playerId, seasonId: selectedSeasonId }),
    };
}
