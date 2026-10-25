import { useEffect, useState } from 'react';
import type { Formation } from '../../../tactics/types';
import { formatOptionLabel } from '../../../tactics/utils';
import { playerDropTarget, startPlayerDrag } from '../dragAndDrop';
import type { DraggedPlayer } from '../dragAndDrop';
import { draftFromMatch, reassignToFormation, shortName } from '../tactics';
import type { LiveMatchData, LivePlayer, TacticsDraft } from '../types';

/**
 * The user's formation on a pitch. While the match is paused, players can be dragged between positions, substitutes
 * dragged onto a player to bring them on, and the formation and mentality changed; position, formation and mentality
 * changes apply together, substitutions straight away.
 */
export default function TacticsTab({
    match,
    formations,
    mentalities,
    editable,
    busy,
    onApply,
    onSubstitute,
}: {
    match: LiveMatchData;
    formations: Formation[];
    mentalities: string[];
    editable: boolean;
    busy: boolean;
    onApply: (draft: TacticsDraft) => Promise<boolean>;
    onSubstitute: (playerOffId: number, playerOnId: number) => Promise<boolean>;
}) {
    const team = match[match.user_side];
    const [draft, setDraft] = useState(() => draftFromMatch(match, formations));
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (!dirty) {
            setDraft(draftFromMatch(match, formations));
        }
    }, [match, formations, dirty]);

    if (draft === null) {
        return <p>No formations are available.</p>;
    }

    const formation = formations.find((option) => option.id === draft.formationId) ?? formations[0];
    const players = new Map<number, LivePlayer>(
        [...team.on_pitch, ...team.bench]
            .filter((player) => player.player_id !== null)
            .map((player) => [player.player_id as number, player]),
    );

    function editDraft(change: Partial<TacticsDraft>) {
        setDraft((current) => (current ? { ...current, ...change } : current));
        setDirty(true);
    }

    function changeFormation(formationId: number) {
        const next = formations.find((option) => option.id === formationId);

        if (!next || !draft) {
            return;
        }

        editDraft({
            formationId,
            assignments: reassignToFormation(
                formation.slots.map((slot) => ({
                    position: slot.position,
                    playerId: draft.assignments[slot.slot] ?? null,
                })),
                next,
            ),
        });
    }

    /** Brings a substitute on in place of a player on the pitch and keeps them in that player's place in the draft. */
    async function substituteInDraft(playerOffId: number, playerOnId: number) {
        if (!(await onSubstitute(playerOffId, playerOnId))) {
            return;
        }

        setDraft((current) => {
            if (!current) {
                return current;
            }

            const assignments = { ...current.assignments };

            for (const [slot, playerId] of Object.entries(assignments)) {
                if (playerId === playerOffId) {
                    assignments[slot] = playerOnId;
                }
            }

            return { ...current, assignments };
        });
    }

    function dropOnSlot(slot: string, dragged: DraggedPlayer) {
        const occupant = draft?.assignments[slot] ?? null;

        if (dragged.from === 'bench') {
            if (occupant !== null) {
                void substituteInDraft(occupant, dragged.playerId);
            }

            return;
        }

        if (dragged.slot && dragged.slot !== slot && draft) {
            editDraft({
                assignments: {
                    ...draft.assignments,
                    [slot]: dragged.playerId,
                    [dragged.slot]: occupant,
                },
            });
        }
    }

    async function apply() {
        if (draft && (await onApply(draft))) {
            setDirty(false);
        }
    }

    return (
        <div className="grid gap-6 xl:grid-cols-[3fr_2fr]">
            <section
                aria-label="Pitch"
                className="relative min-h-[560px] overflow-hidden rounded border border-white/10 bg-gradient-to-b from-emerald-700 via-emerald-800 to-emerald-700"
            >
                <div aria-hidden className="pointer-events-none absolute inset-0 opacity-30">
                    <div className="absolute inset-4 border-2 border-white" />
                    <div className="absolute inset-x-0 top-1/2 h-px bg-white" />
                    <div className="absolute top-1/2 left-1/2 size-24 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white" />
                </div>

                {formation.slots.map((slot) => {
                    const playerId = draft.assignments[slot.slot] ?? null;
                    const player = playerId === null ? null : players.get(playerId);

                    return (
                        <div
                            key={slot.slot}
                            {...(editable ? playerDropTarget((dragged) => dropOnSlot(slot.slot, dragged)) : {})}
                            className="absolute flex w-24 -translate-x-1/2 -translate-y-1/2 flex-col items-center"
                            style={{ left: `${slot.x}%`, top: `${slot.y}%` }}
                        >
                            {player ? (
                                <span
                                    draggable={editable}
                                    onDragStart={(event) =>
                                        startPlayerDrag(event, {
                                            playerId: playerId as number,
                                            from: 'pitch',
                                            slot: slot.slot,
                                        })
                                    }
                                    className={`flex size-10 items-center justify-center rounded-full border-2 border-white/60 bg-red-900 text-sm font-bold text-white ${
                                        editable ? 'cursor-grab active:cursor-grabbing' : ''
                                    }`}
                                    title={player.name ?? undefined}
                                >
                                    {player.stats ? player.stats.rating.toFixed(1) : slot.position}
                                </span>
                            ) : (
                                <span className="flex size-10 items-center justify-center rounded-full border-2 border-dashed border-white/60 text-xs text-white/80">
                                    —
                                </span>
                            )}
                            <span className="mt-0.5 text-xs font-bold text-white">{slot.position}</span>
                            <span className="max-w-24 truncate text-xs font-bold text-[#f5f000]">
                                {player ? shortName(player.name) : 'Empty'}
                            </span>
                        </div>
                    );
                })}
            </section>

            <div className="flex flex-col gap-6">
                <section className="border border-[#3355dd]/50" aria-label="Team instructions">
                    <h2 className="bg-[#101358] px-5 py-3 font-bold text-[#f5f000]">Tactics</h2>
                    <div className="flex flex-col gap-4 p-5">
                        {!editable && (
                            <p className="text-[#c8ffb0]">
                                {match.finished
                                    ? 'The match has finished.'
                                    : 'Pause the match to change tactics.'}
                            </p>
                        )}
                        <label className="flex flex-col gap-1">
                            <span className="text-sm font-bold">Formation</span>
                            <select
                                value={draft.formationId}
                                disabled={!editable}
                                onChange={(event) => changeFormation(Number(event.target.value))}
                                className="border border-[#3355dd] bg-black px-3 py-2 disabled:opacity-50"
                            >
                                {formations.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name} ({formatOptionLabel(option.tactical_tendency)})
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-sm font-bold">Mentality</span>
                            <select
                                value={draft.mentality}
                                disabled={!editable}
                                onChange={(event) => editDraft({ mentality: event.target.value })}
                                className="border border-[#3355dd] bg-black px-3 py-2 disabled:opacity-50"
                            >
                                {mentalities.map((option) => (
                                    <option key={option} value={option}>
                                        {formatOptionLabel(option)}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <p className="text-sm text-white/70">
                            Drag players between positions to swap them, or drag a substitute onto a player to bring
                            them on.
                        </p>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={apply}
                                disabled={!editable || !dirty || busy}
                                className="flex-1 border-2 border-[#f5f000] bg-[#f5f000] px-4 py-2 font-bold text-black hover:bg-white disabled:opacity-40 disabled:hover:bg-[#f5f000]"
                            >
                                Apply changes
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setDirty(false);
                                    setDraft(draftFromMatch(match, formations));
                                }}
                                disabled={!dirty || busy}
                                className="border-2 border-[#3355dd] px-4 py-2 font-bold text-[#c8ffb0] hover:bg-[#3355dd]/40 disabled:opacity-40"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </section>

                <section className="border border-[#3355dd]/50" aria-label="Substitutes">
                    <h2 className="bg-[#101358] px-5 py-3 font-bold text-[#f5f000]">
                        Substitutes ({team.substitutions_used}/{match.max_substitutions} used)
                    </h2>
                    {team.bench.length === 0 ? (
                        <p className="px-5 py-3 text-white/70">No substitutes left on the bench.</p>
                    ) : (
                        <ul className="divide-y divide-white/10">
                            {team.bench.map((player) => (
                                <li
                                    key={player.player_id}
                                    draggable={editable}
                                    onDragStart={(event) =>
                                        startPlayerDrag(event, { playerId: player.player_id as number, from: 'bench' })
                                    }
                                    {...(editable
                                        ? playerDropTarget(
                                              (dragged) =>
                                                  dragged.from === 'pitch' &&
                                                  void substituteInDraft(dragged.playerId, player.player_id as number),
                                          )
                                        : {})}
                                    className={`flex justify-between px-5 py-2 ${editable ? 'cursor-grab hover:bg-[#3355dd]/30' : ''}`}
                                >
                                    <span>
                                        <span className="mr-3 font-bold text-[#c8ffb0]">{player.position}</span>
                                        {player.name}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </div>
    );
}
