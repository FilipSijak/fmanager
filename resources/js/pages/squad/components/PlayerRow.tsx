import type { SquadPlayer } from '../types';
import { formatMoney } from '../utils';

export default function PlayerRow({ player }: { player: SquadPlayer }) {
    return (
        <div className="flex items-center gap-2 py-1.5">
            <span className="size-5 shrink-0 rounded-sm bg-[#0000a5]" />
            <span className="flex-1 truncate text-[15px] font-bold text-white">
                {player.first_name} {player.last_name}
            </span>
            <span className="shrink-0 text-xs text-[#9aa0c0]">
                {formatMoney(player.value)}
            </span>
            <span className="shrink-0 text-sm font-bold text-[#f5f000]">
                {player.position}
            </span>
        </div>
    );
}
