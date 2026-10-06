import { Link } from '@inertiajs/react';
import { playerProfile } from '@/routes';
import type { SortOption, SquadPlayer } from '../types';
import { formatMoney, LINEUP_CHIP_DRAG_TYPE } from '../utils';

export default function PlayerRow({
    player,
    assignedChipId,
    sortBy,
    onBoxClick,
    onDropChip,
}: {
    player: SquadPlayer;
    assignedChipId: string | null;
    sortBy: SortOption;
    onBoxClick: () => void;
    onDropChip: (chipId: string) => void;
}) {
    const metric =
        sortBy === 'contract_expiry'
            ? (player.contract_end ?? 'No contract')
            : formatMoney(player.value);
    return (
        <div className="flex items-center gap-2 py-1.5">
            <button
                type="button"
                onClick={onBoxClick}
                onDragOver={(event) => event.preventDefault()}
                onDrop={(event) => {
                    event.preventDefault();
                    const chipId = event.dataTransfer.getData(
                        LINEUP_CHIP_DRAG_TYPE,
                    );
                    if (chipId) {
                        onDropChip(chipId);
                    }
                }}
                title={
                    assignedChipId
                        ? `Assigned to ${assignedChipId} - click to clear`
                        : 'Click to assign the next free position, or drag a position here'
                }
                aria-label={
                    assignedChipId
                        ? `Position ${assignedChipId} assigned to ${player.first_name} ${player.last_name}`
                        : `Assign a position to ${player.first_name} ${player.last_name}`
                }
                className={`flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm text-[8px] font-bold text-white ${
                    assignedChipId
                        ? 'bg-emerald-600 hover:bg-emerald-500'
                        : 'bg-[#0000a5] hover:bg-[#0000d0]'
                }`}
            >
                {assignedChipId?.replace('-', '')}
            </button>
            <Link
                href={playerProfile.url(player.id)}
                className="flex min-w-0 flex-1 items-center gap-2 hover:opacity-80"
            >
                <span className="flex-1 truncate text-[15px] font-bold text-white">
                    {player.first_name} {player.last_name}
                </span>
                <span className="shrink-0 text-xs text-[#9aa0c0]">
                    {metric}
                </span>
                <span className="shrink-0 text-sm font-bold text-[#f5f000]">
                    {player.position}
                </span>
            </Link>
        </div>
    );
}
