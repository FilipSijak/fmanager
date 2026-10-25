import type { DraggedPlayer } from '../dragAndDrop';
import { playerDropTarget, startPlayerDrag } from '../dragAndDrop';
import type { LivePlayer, LiveSide } from '../types';

/** Lets the user pick, or drag, a player on the pitch and a substitute to swap. */
type Selection = {
    playerOffId: number | null;
    playerOnId: number | null;
    onSelectOff: (playerId: number) => void;
    onSelectOn: (playerId: number) => void;
    onSubstitute: (playerOffId: number, playerOnId: number) => void;
};

const STATUS_LABELS: Partial<Record<LivePlayer['status'], string>> = {
    substituted: 'Subbed off',
    sent_off: 'Sent off',
    empty: 'Empty',
};

function passAccuracy(player: LivePlayer): string {
    const stats = player.stats;

    if (!stats || stats.passes_attempted === 0) {
        return '–';
    }

    return `${Math.round((100 * stats.passes_completed) / stats.passes_attempted)}%`;
}

function Cards({ player }: { player: LivePlayer }) {
    const stats = player.stats;

    if (!stats || (stats.yellow_cards === 0 && stats.red_cards === 0)) {
        return null;
    }

    return (
        <span className="ml-2 inline-flex gap-0.5 align-middle">
            <span className="sr-only">
                {stats.yellow_cards} yellow, {stats.red_cards} red
            </span>
            {stats.yellow_cards > 0 && (
                <span className="inline-block h-3 w-2 bg-yellow-400" />
            )}
            {stats.red_cards > 0 && (
                <span className="inline-block h-3 w-2 bg-red-600" />
            )}
        </span>
    );
}

function PlayerRows({
    title,
    players,
    selectable,
    selectedId,
    onSelect,
    dragFrom,
    onDropPlayer,
}: {
    title: string;
    players: LivePlayer[];
    selectable: boolean;
    selectedId: number | null;
    onSelect?: (playerId: number) => void;
    /** Rows can be dragged out of this group as players from the pitch or the bench. */
    dragFrom?: DraggedPlayer['from'];
    /** A player dropped onto a row of this group. */
    onDropPlayer?: (player: DraggedPlayer, target: number) => void;
}) {
    if (players.length === 0) {
        return null;
    }

    return (
        <>
            <tr>
                <th
                    colSpan={12}
                    className="bg-black/40 px-3 py-1.5 text-left text-xs font-bold tracking-wide text-[#c8ffb0] uppercase"
                >
                    {title}
                </th>
            </tr>
            {players.map((player, index) => {
                const isSelectable = selectable && player.player_id !== null;
                const isSelected =
                    player.player_id !== null &&
                    player.player_id === selectedId;
                const stats = player.stats;

                return (
                    <tr
                        key={player.player_id ?? `empty-${index}`}
                        draggable={isSelectable && dragFrom !== undefined}
                        onDragStart={(event) =>
                            dragFrom &&
                            startPlayerDrag(event, {
                                playerId: player.player_id as number,
                                from: dragFrom,
                            })
                        }
                        {...(isSelectable && onDropPlayer
                            ? playerDropTarget((dragged) =>
                                  onDropPlayer(
                                      dragged,
                                      player.player_id as number,
                                  ),
                              )
                            : {})}
                        onClick={
                            isSelectable && onSelect
                                ? () => onSelect(player.player_id as number)
                                : undefined
                        }
                        className={`border-t border-white/10 tabular-nums ${
                            isSelected
                                ? 'bg-[#f5f000] text-black'
                                : isSelectable
                                  ? 'cursor-pointer hover:bg-[#3355dd]/30'
                                  : ''
                        } ${player.status === 'empty' || player.status === 'substituted' || player.status === 'sent_off' ? 'opacity-60' : ''}`}
                    >
                        <td className="px-3 py-1.5 font-bold">
                            {player.position}
                        </td>
                        <td className="px-3 py-1.5 text-left">
                            {player.name ?? '—'}
                            {STATUS_LABELS[player.status] && (
                                <span className="ml-2 text-xs">
                                    ({STATUS_LABELS[player.status]})
                                </span>
                            )}
                            <Cards player={player} />
                        </td>
                        <td className="px-2 py-1.5">
                            {stats ? `${stats.minutes_played}′` : ''}
                        </td>
                        <td className="px-2 py-1.5">{stats?.goals ?? ''}</td>
                        <td className="px-2 py-1.5">{stats?.assists ?? ''}</td>
                        <td className="px-2 py-1.5">
                            {stats
                                ? `${stats.shots}/${stats.shots_on_target}`
                                : ''}
                        </td>
                        <td className="px-2 py-1.5">
                            {stats?.key_passes ?? ''}
                        </td>
                        <td className="px-2 py-1.5">
                            {stats ? passAccuracy(player) : ''}
                        </td>
                        <td className="px-2 py-1.5">
                            {stats
                                ? `${stats.tackles_won}/${stats.tackles_attempted}`
                                : ''}
                        </td>
                        <td className="px-2 py-1.5">
                            {stats?.interceptions ?? ''}
                        </td>
                        <td className="px-2 py-1.5">
                            {stats?.position === 'GK' ? stats.saves : ''}
                        </td>
                        <td className="px-3 py-1.5 text-right font-bold">
                            {stats ? stats.rating.toFixed(1) : ''}
                        </td>
                    </tr>
                );
            })}
        </>
    );
}

/**
 * One side's players with their stats so far. For the user's side, while substitutions are allowed, a player on the
 * pitch and one on the bench can be picked to swap, or one dragged onto the other.
 */
export default function TeamSheet({
    side,
    selection,
}: {
    side: LiveSide;
    selection: Selection | null;
}) {
    return (
        <section
            className="overflow-x-auto border border-[#3355dd]/50"
            aria-label={`${side.name} team`}
        >
            <h2 className="bg-[#101358] px-4 py-3 font-bold text-[#f5f000]">
                {side.name}
            </h2>
            <table className="w-full text-center text-sm">
                <thead className="text-xs text-[#c8ffb0]">
                    <tr>
                        <th className="px-3 py-2 text-left">Pos</th>
                        <th className="px-3 py-2 text-left">Player</th>
                        <th className="px-2 py-2" title="Minutes played">
                            Min
                        </th>
                        <th className="px-2 py-2" title="Goals">
                            G
                        </th>
                        <th className="px-2 py-2" title="Assists">
                            A
                        </th>
                        <th className="px-2 py-2" title="Shots / on target">
                            Sh
                        </th>
                        <th className="px-2 py-2" title="Key passes">
                            KP
                        </th>
                        <th className="px-2 py-2" title="Pass completion">
                            Pass
                        </th>
                        <th
                            className="px-2 py-2"
                            title="Tackles won / attempted"
                        >
                            Tkl
                        </th>
                        <th className="px-2 py-2" title="Interceptions">
                            Int
                        </th>
                        <th className="px-2 py-2" title="Saves">
                            Sv
                        </th>
                        <th
                            className="px-3 py-2 text-right"
                            title="Match rating"
                        >
                            Rtg
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <PlayerRows
                        title="On the pitch"
                        players={side.on_pitch}
                        selectable={selection !== null}
                        selectedId={selection?.playerOffId ?? null}
                        onSelect={selection?.onSelectOff}
                        dragFrom="pitch"
                        onDropPlayer={(dragged, playerOffId) =>
                            dragged.from === 'bench' &&
                            selection?.onSubstitute(
                                playerOffId,
                                dragged.playerId,
                            )
                        }
                    />
                    <PlayerRows
                        title="Substitutes"
                        players={side.bench}
                        selectable={selection !== null}
                        selectedId={selection?.playerOnId ?? null}
                        onSelect={selection?.onSelectOn}
                        dragFrom="bench"
                        onDropPlayer={(dragged, playerOnId) =>
                            dragged.from === 'pitch' &&
                            selection?.onSubstitute(
                                dragged.playerId,
                                playerOnId,
                            )
                        }
                    />
                    <PlayerRows
                        title="Off the pitch"
                        players={side.finished}
                        selectable={false}
                        selectedId={null}
                    />
                </tbody>
            </table>
        </section>
    );
}
