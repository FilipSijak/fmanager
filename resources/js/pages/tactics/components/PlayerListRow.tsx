import { Link } from '@inertiajs/react';
import { playerProfile } from '@/routes';
import type { LineupPlayer } from '../types';

export default function PlayerListRow({
    lineupPlayer,
    isStarter,
}: {
    lineupPlayer: LineupPlayer;
    isStarter: boolean;
}) {
    const { number, player } = lineupPlayer;

    return (
        <Link
            href={playerProfile.url(player.id)}
            className="flex items-center gap-2 bg-[#0c0c14] px-2 py-1.5 hover:opacity-80"
        >
            <span className="w-6 shrink-0 text-center text-sm font-bold text-[#f5f000]">
                {number}
            </span>
            <span
                className={`flex-1 truncate text-sm font-semibold ${isStarter ? 'text-white' : 'text-slate-400'}`}
            >
                {player.first_name} {player.last_name}
            </span>
            <span className="w-10 shrink-0 text-right text-sm font-bold text-[#f5f000]">
                {player.position}
            </span>
        </Link>
    );
}
