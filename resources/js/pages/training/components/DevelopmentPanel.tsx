import { Link } from '@inertiajs/react';
import { playerProfile } from '@/routes';
import type { Category, TrainingPlayer } from '../types';
import { buttonClass, fieldClass } from '../utils';

export default function DevelopmentPanel({
    players,
    categories,
    selectedId,
    onSelect,
}: {
    players: TrainingPlayer[];
    categories: Category[];
    selectedId: number | null;
    onSelect: (id: number) => void;
}) {
    const player = players.find((item) => item.id === selectedId) ?? players[0];
    if (!player)
        return (
            <p className="border border-dashed border-[#3355dd] p-10 text-center text-slate-400">
                There are no active players to develop.
            </p>
        );

    return (
        <section className="flex flex-col gap-5">
            <div className="flex flex-wrap items-end justify-between gap-4 border border-[#3355dd] bg-[#10102c] p-5">
                <label className="flex min-w-64 flex-col gap-2 text-sm text-slate-300">
                    Player
                    <select
                        value={player.id}
                        onChange={(event) =>
                            onSelect(Number(event.target.value))
                        }
                        className={fieldClass}
                    >
                        {players.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ({item.position})
                            </option>
                        ))}
                    </select>
                </label>
                <Link
                    href={playerProfile.url(player.id)}
                    className={buttonClass}
                >
                    Player profile
                </Link>
                <p className="w-full text-sm text-slate-400">
                    Current attribute ratings and accumulated progress toward
                    the next point. Tactical training develops mental
                    attributes. Progress is slower in this category.
                </p>
                <p className="text-xs text-slate-500">
                    {player.last_processed_at
                        ? `Last session processed: ${player.last_processed_at.slice(0, 10)}`
                        : 'No training session has been processed yet.'}{' '}
                    Attribute growth also depends on the player’s development
                    ceiling.
                </p>
            </div>
            <div className="grid gap-5 xl:grid-cols-2">
                {categories
                    .filter(
                        (category) =>
                            category.key !== 'goalkeeping' ||
                            player.position === 'GK',
                    )
                    .map((category) => (
                        <section
                            key={category.id}
                            className="border border-[#3355dd] bg-[#10102c]"
                        >
                            <h2 className="border-b border-[#3355dd] px-5 py-3 font-bold text-cyan-300">
                                {category.name}
                                {category.key === 'tactical' ? ' / Mental' : ''}
                            </h2>
                            <div className="flex items-center justify-between px-5 pt-3 text-xs text-slate-500">
                                <span>Attribute / 20</span>
                                <span>Progress / {category.threshold}</span>
                            </div>
                            <div className="divide-y divide-white/5 px-5">
                                {player.attributes[category.key].map(
                                    (attribute) => (
                                        <div
                                            key={attribute.key}
                                            className="grid grid-cols-[1fr_2rem_minmax(90px,35%)] items-center gap-3 py-3 text-sm"
                                        >
                                            <span className="text-slate-300">
                                                {attribute.name}
                                            </span>
                                            <strong className="text-right text-[#f5f000]">
                                                {attribute.value}
                                            </strong>
                                            <div className="flex items-center gap-2">
                                                <progress
                                                    aria-label={`${attribute.name} progress toward next point`}
                                                    value={attribute.progress}
                                                    max={category.threshold}
                                                    className="h-2 w-full accent-cyan-300"
                                                />
                                                <span className="w-12 shrink-0 text-right text-xs text-slate-400">
                                                    {Math.round(
                                                        (attribute.progress /
                                                            category.threshold) *
                                                            100,
                                                    )}
                                                    %
                                                </span>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </section>
                    ))}
            </div>
        </section>
    );
}
