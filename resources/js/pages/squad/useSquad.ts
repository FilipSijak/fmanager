import { useEffect, useState } from 'react';
import { squad as fetchSquad } from '@/actions/App/Http/Controllers/ClubController';
import { index as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import api from '@/api';
import type { SquadPlayer } from './types';

export function useSquad() {
    const [clubName, setClubName] = useState<string | null>(null);
    const [instanceDate, setInstanceDate] = useState<string | null>(null);
    const [players, setPlayers] = useState<SquadPlayer[] | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    useEffect(() => {
        api.get(showDashboard.url())
            .then((response) => {
                const club = response.data.data.club as {
                    id: number;
                    name: string;
                };
                const instance = response.data.data.instance as {
                    date: string;
                };
                setClubName(club.name);
                setInstanceDate(instance.date);

                return api.get(fetchSquad.url(club.id));
            })
            .then((response) => {
                setPlayers(response.data.data as SquadPlayer[]);
            })
            .catch(() => setLoadError('Unable to load the squad.'));
    }, []);

    return { clubName, instanceDate, players, loadError };
}
