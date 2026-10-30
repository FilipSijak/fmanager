import { Head, Link } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import { playerProfile, squad } from '@/routes';
import DevelopmentPanel from './components/DevelopmentPanel';
import PlayerTrainingDialog from './components/PlayerTrainingDialog';
import type { TrainingProps } from './types';
import { useTraining } from './useTraining';
import { buttonClass, conditionColor, fieldClass, planName } from './utils';

export default function Training({
    club,
    date,
    players,
    categories,
    intensities,
    presets,
}: TrainingProps) {
    const [tab, setTab] = useState<'training' | 'development'>('training');
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState('all');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [presetId, setPresetId] = useState('medium');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [developmentId, setDevelopmentId] = useState<number | null>(
        players[0]?.id ?? null,
    );
    const { save, saving, error, notice, clearMessages } = useTraining();
    const editingPlayer = players.find((player) => player.id === editingId);
    const visible = players.filter(
        (player) =>
            player.name.toLowerCase().includes(query.toLowerCase()) &&
            (filter === 'all' ||
                (filter === 'attention' &&
                    (player.condition < 70 || player.is_injured)) ||
                (filter === 'rest' && player.resting) ||
                (filter === 'goalkeepers' && player.position === 'GK')),
    );
    const selected = players.filter((player) =>
        selectedIds.includes(player.id),
    );
    const preset = presets.find((item) => item.id === presetId);
    const invalidGoalkeepingSelection =
        preset?.goalkeeper_only &&
        selected.some((player) => player.position !== 'GK');
    const averageCondition = players.length
        ? Math.round(
              players.reduce((total, player) => total + player.condition, 0) /
                  players.length,
          )
        : 0;

    function togglePlayer(id: number) {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    }

    async function applyPreset(event: FormEvent) {
        event.preventDefault();
        if (
            preset &&
            !invalidGoalkeepingSelection &&
            (await save(
                selected.map((player) => player.id),
                preset.schedule,
            ))
        )
            setSelectedIds([]);
    }

    return (
        <GameLayout active="Training">
            <Head title={`${club.name} - Training`} />
            <main
                className="min-h-screen flex-1 bg-[#000018] px-6 pt-16 pb-8 text-white"
                style={{ fontFamily: "'Courier New', monospace" }}
            >
                <div className="mx-auto flex max-w-7xl flex-col gap-6">
                    <header className="flex flex-wrap items-start justify-between gap-4 border-b border-[#3355dd] pb-5">
                        <div>
                            <p className="text-sm text-cyan-300">
                                {club.name} / {date}
                            </p>
                            <h1 className="mt-2 text-3xl font-bold text-[#f5f000]">
                                Training & development
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                                Build your players. Balance development with
                                recovery, and prepare the squad for the next
                                match.
                            </p>
                        </div>
                        <Link href={squad.url()} className={buttonClass}>
                            View squad
                        </Link>
                    </header>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            ['Players in training', players.length],
                            [
                                'Average condition',
                                players.length ? `${averageCondition}%` : '—',
                            ],
                            [
                                'Need recovery / injured',
                                players.filter(
                                    (player) =>
                                        player.condition < 70 ||
                                        player.is_injured,
                                ).length,
                            ],
                            [
                                'On full rest',
                                players.filter((player) => player.resting)
                                    .length,
                            ],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="border border-[#3355dd] bg-[#10102c] p-4"
                            >
                                <p className="text-xs text-slate-400">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-bold text-[#f5f000]">
                                    {value}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <nav aria-label="Training views" className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setTab('training')}
                                aria-pressed={tab === 'training'}
                                className={`${buttonClass} ${tab === 'training' ? 'bg-[#3355dd]/40' : ''}`}
                            >
                                Training plans
                            </button>
                            <button
                                type="button"
                                onClick={() => setTab('development')}
                                aria-pressed={tab === 'development'}
                                className={`${buttonClass} ${tab === 'development' ? 'bg-[#3355dd]/40' : ''}`}
                            >
                                Player development
                            </button>
                        </nav>
                        <p className="text-xs text-slate-500">
                            Settings take effect when you advance the game.
                        </p>
                    </div>
                    {notice && (
                        <p
                            role="status"
                            className="border border-[#39ff14]/40 bg-[#39ff14]/5 px-4 py-3 text-sm text-[#39ff14]"
                        >
                            {notice}
                        </p>
                    )}
                    {error && !editingPlayer && (
                        <p
                            role="alert"
                            className="border border-red-400/40 px-4 py-3 text-sm text-red-400"
                        >
                            {error}
                        </p>
                    )}
                    {tab === 'development' ? (
                        <DevelopmentPanel
                            players={players}
                            categories={categories}
                            selectedId={developmentId}
                            onSelect={setDevelopmentId}
                        />
                    ) : (
                        <>
                            <div className="flex flex-wrap gap-4">
                                <label className="flex min-w-60 flex-1 flex-col gap-2 text-xs text-slate-400">
                                    Find a player
                                    <input
                                        type="search"
                                        placeholder="Search your squad…"
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        className={fieldClass}
                                    />
                                </label>
                                <label className="flex min-w-52 flex-col gap-2 text-xs text-slate-400">
                                    Show
                                    <select
                                        value={filter}
                                        onChange={(event) =>
                                            setFilter(event.target.value)
                                        }
                                        className={fieldClass}
                                    >
                                        <option value="all">All players</option>
                                        <option value="attention">
                                            Recovery / injuries
                                        </option>
                                        <option value="rest">
                                            On full rest
                                        </option>
                                        <option value="goalkeepers">
                                            Goalkeepers
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <form
                                onSubmit={applyPreset}
                                className="flex flex-wrap items-end gap-4 border border-[#3355dd] bg-[#10102c] p-4"
                            >
                                <p className="self-center text-sm text-cyan-300">
                                    {selected.length} selected
                                </p>
                                <label className="flex min-w-56 flex-1 flex-col gap-2 text-xs text-slate-400">
                                    Apply a shared plan
                                    <select
                                        disabled={saving}
                                        value={presetId}
                                        onChange={(event) =>
                                            setPresetId(event.target.value)
                                        }
                                        className={fieldClass}
                                    >
                                        {presets.map((item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <button
                                    type="submit"
                                    disabled={
                                        saving ||
                                        selected.length === 0 ||
                                        !!invalidGoalkeepingSelection
                                    }
                                    className={buttonClass}
                                >
                                    {saving ? 'Saving…' : 'Apply to selected'}
                                </button>
                                {selected.length > 0 && (
                                    <button
                                        type="button"
                                        disabled={saving}
                                        onClick={() => setSelectedIds([])}
                                        className={buttonClass}
                                    >
                                        Clear
                                    </button>
                                )}
                                {invalidGoalkeepingSelection && (
                                    <p className="w-full text-xs text-amber-300">
                                        Goalkeeping focus is available for
                                        goalkeepers. Change the selection or
                                        choose another plan.
                                    </p>
                                )}
                            </form>
                            <div className="overflow-x-auto border border-[#3355dd]">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-[#181838] text-xs text-cyan-300">
                                        <tr>
                                            <th className="p-4">
                                                <input
                                                    type="checkbox"
                                                    aria-label="Select all shown players"
                                                    disabled={
                                                        saving ||
                                                        visible.length === 0
                                                    }
                                                    checked={
                                                        visible.length > 0 &&
                                                        visible.every(
                                                            (player) =>
                                                                selectedIds.includes(
                                                                    player.id,
                                                                ),
                                                        )
                                                    }
                                                    onChange={(event) =>
                                                        setSelectedIds(
                                                            (current) =>
                                                                event.target
                                                                    .checked
                                                                    ? [
                                                                          ...new Set(
                                                                              [
                                                                                  ...current,
                                                                                  ...visible.map(
                                                                                      (
                                                                                          player,
                                                                                      ) =>
                                                                                          player.id,
                                                                                  ),
                                                                              ],
                                                                          ),
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              !visible.some(
                                                                                  (
                                                                                      player,
                                                                                  ) =>
                                                                                      player.id ===
                                                                                      id,
                                                                              ),
                                                                      ),
                                                        )
                                                    }
                                                />
                                            </th>
                                            <th className="py-4 pr-4">
                                                Player
                                            </th>
                                            <th className="p-4">Condition</th>
                                            <th className="p-4">Plan</th>
                                            <th className="p-4">Workload</th>
                                            <th className="p-4">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#3355dd]/30">
                                        {visible.map((player) => (
                                            <tr
                                                key={player.id}
                                                className="hover:bg-white/5"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`Select ${player.name}`}
                                                        checked={selectedIds.includes(
                                                            player.id,
                                                        )}
                                                        disabled={saving}
                                                        onChange={() =>
                                                            togglePlayer(
                                                                player.id,
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="min-w-48 py-4 pr-4">
                                                    <Link
                                                        href={playerProfile.url(
                                                            player.id,
                                                        )}
                                                        className="font-bold text-cyan-300 hover:underline"
                                                    >
                                                        {player.name}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {player.position}
                                                        {player.is_injured
                                                            ? ' · Injured'
                                                            : ''}
                                                    </p>
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex items-center gap-2">
                                                        <progress
                                                            aria-label={`${player.name} condition`}
                                                            value={
                                                                player.condition
                                                            }
                                                            max={100}
                                                            className="h-2 w-16 accent-[#39ff14]"
                                                        />
                                                        <strong
                                                            className={conditionColor(
                                                                player.condition,
                                                            )}
                                                        >
                                                            {player.condition}%
                                                        </strong>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap p-4 text-slate-300">
                                                    {planName(player, presets)}
                                                </td>
                                                <td className="p-4">
                                                    <span
                                                        className={
                                                            player.workload ===
                                                            'Heavy'
                                                                ? 'text-amber-300'
                                                                : player.resting
                                                                  ? 'text-[#39ff14]'
                                                                  : 'text-slate-300'
                                                        }
                                                    >
                                                        {player.workload}
                                                    </span>
                                                    <p className="mt-1 whitespace-nowrap text-xs text-slate-500">
                                                        {player.condition_change >
                                                        0
                                                            ? '+'
                                                            : ''}
                                                        {
                                                            player.condition_change
                                                        }{' '}
                                                        condition / session
                                                    </p>
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={saving}
                                                            onClick={() => {
                                                                clearMessages();
                                                                setEditingId(
                                                                    player.id,
                                                                );
                                                            }}
                                                            aria-label={`Edit training for ${player.name}`}
                                                            className={
                                                                buttonClass
                                                            }
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setDevelopmentId(
                                                                    player.id,
                                                                );
                                                                setTab(
                                                                    'development',
                                                                );
                                                            }}
                                                            aria-label={`View development for ${player.name}`}
                                                            className={
                                                                buttonClass
                                                            }
                                                        >
                                                            Progress
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {visible.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="p-10 text-center text-slate-400"
                                                >
                                                    {players.length === 0
                                                        ? 'No active players in this squad.'
                                                        : 'No players match this search or filter.'}
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <section className="border-l-2 border-cyan-300/40 pl-4 text-sm leading-relaxed text-slate-400">
                                <h2 className="font-bold text-cyan-300">
                                    Plan for the next session
                                </h2>
                                <p className="mt-2">
                                    Focus plans train one category hard and keep
                                    the others light. Full rest restores up to
                                    10 condition and preserves progress; choose
                                    a training plan to resume.
                                </p>
                                <p className="mt-2">
                                    Players miss training while injured or below
                                    70% condition. Clubs skip training on
                                    matchdays and recover the day after a match.
                                    Heavy combined schedules can reduce
                                    condition; goalkeeping workload counts for
                                    goalkeepers.
                                </p>
                            </section>
                        </>
                    )}
                </div>
            </main>
            {editingPlayer && (
                <PlayerTrainingDialog
                    key={editingPlayer.id}
                    player={editingPlayer}
                    categories={categories}
                    intensities={intensities}
                    presets={presets}
                    saving={saving}
                    error={error}
                    onSave={save}
                    onClose={() => setEditingId(null)}
                />
            )}
        </GameLayout>
    );
}
