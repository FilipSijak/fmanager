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

    useEffect(() => {
        setPlayer(null);
        setLoadError(null);

        Promise.all([
            api.get(showDashboard.url()),
            api.get(showPlayer.url(playerId)),
        ])
            .then(([dashboardResponse, playerResponse]) => {
                const instance = dashboardResponse.data.data.instance as {
                    date: string;
                };
                setInstanceDate(instance.date);
                setManagedClubId(dashboardResponse.data.data.club?.id ?? null);
                setPlayer(playerResponse.data.data as PlayerProfileData);
            })
            .catch(() => setLoadError('Unable to load this player.'));
    }, [playerId]);

    return { player, instanceDate, loadError, managedClubId };
}
