import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { playerProfile } from '@/routes';
import type { LineupPlayer } from '../types';
import { LINEUP_SLOT_DRAG_TYPE } from '../utils';

export default function PlayerListRow({
    lineupPlayer,
    isStarter,
    onSwap,
}: {
    lineupPlayer: LineupPlayer;
    isStarter: boolean;
    /** Called with the slot id of the row dropped onto this one. */
    onSwap: (fromSlotId: string) => void;
}) {
    const { slotId, number, lineupPosition, player } = lineupPlayer;
    const [isDragOver, setIsDragOver] = useState(false);

    return (
        <div
            draggable
            onDragStart={(event) => {
                event.dataTransfer.setData(LINEUP_SLOT_DRAG_TYPE, slotId);
                event.dataTransfer.effectAllowed = 'move';
            }}
            onDragOver={(event) => {
                if (event.dataTransfer.types.includes(LINEUP_SLOT_DRAG_TYPE)) {
                    event.preventDefault();
                    setIsDragOver(true);
                }
            }}
            onDragLeave={() => setIsDragOver(false)}
            onDrop={(event) => {
                event.preventDefault();
                setIsDragOver(false);
                const fromSlotId = event.dataTransfer.getData(
                    LINEUP_SLOT_DRAG_TYPE,
                );
                if (fromSlotId) {
                    onSwap(fromSlotId);
                }
            }}
            title="Drag onto another player to swap positions"
            className={`flex cursor-grab items-center gap-2 px-2 py-1.5 active:cursor-grabbing ${
                isDragOver
                    ? 'bg-[#0031a5] ring-2 ring-[#f5f000] ring-inset'
                    : 'bg-[#0c0c14]'
            }`}
        >
            <span className="w-6 shrink-0 text-center text-sm font-bold text-[#f5f000]">
                {number}
            </span>
            <Link
                href={playerProfile.url(player.id)}
                draggable={false}
                className={`flex-1 truncate text-sm font-semibold hover:underline ${isStarter ? 'text-white' : 'text-slate-400'}`}
            >
                {player.first_name} {player.last_name}
            </Link>
            <span className="w-10 shrink-0 text-right text-sm font-bold text-[#f5f000]">
                {lineupPosition}
            </span>
        </div>
    );
}
