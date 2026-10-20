import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import { show } from '@/routes/competitions';
import KnockoutRounds, { roundName } from './KnockoutRounds';
import MatchList from './MatchList';
import StandingsTable from './StandingsTable';
import type { CompetitionPageProps } from './types';

export default function Competition(props: CompetitionPageProps) {
    return (
        <CompetitionScreen
            key={`${props.competition?.id ?? 'empty'}-${props.season?.id}`}
            {...props}
        />
    );
}

function CompetitionScreen({
    competitions,
    competition,
    season,
    seasons,
    currentSeasonId,
    clubId,
    table,
    groups,
    matches,
    rounds,
}: CompetitionPageProps) {
    const isLeague = competition?.type === 'league';
    const historical = season !== null && season.id !== currentSeasonId;
    const hasGroups = groups.length > 0 || competition?.groups === 1;
    const tabs = [
        ...(isLeague
            ? ['Table']
            : [...(hasGroups ? ['Groups'] : []), 'Rounds']),
        'Results',
        'Fixtures',
    ];
    const [activeTab, setActiveTab] = useState(
        isLeague
            ? 'Table'
            : hasGroups && rounds.length === 0
              ? 'Groups'
              : 'Rounds',
    );
    const [stageFilter, setStageFilter] = useState('all');
    const [clubOnly, setClubOnly] = useState(false);
    const roundNumbers = [
        ...new Set(rounds.map((round) => round.round_number)),
    ];
    const selectedRound = stageFilter.startsWith('round:')
        ? Number(stageFilter.slice(6))
        : null;
    const selectedGroup = stageFilter.startsWith('group:')
        ? Number(stageFilter.slice(6))
        : null;
    const tieIds = new Set(
        rounds
            .filter((round) => round.round_number === selectedRound)
            .flatMap((round) => round.ties.map((tie) => tie.id)),
    );
    const filteredMatches = matches.filter(
        (match) =>
            (!clubOnly ||
                match.hometeam_id === clubId ||
                match.awayteam_id === clubId) &&
            (selectedGroup === null || match.group_id === selectedGroup) &&
            (selectedRound === null ||
                (match.knockout_tie_id !== null &&
                    tieIds.has(match.knockout_tie_id))),
    );
    const results = filteredMatches
        .filter((match) =>
            ['completed', 'cancelled', 'abandoned'].includes(match.status),
        )
        .reverse();
    const fixtures = filteredMatches.filter((match) =>
        ['scheduled', 'postponed', 'in_progress'].includes(match.status),
    );
    const seasonLabel = season
        ? `${season.start_date.slice(0, 4)}/${season.end_date.slice(2, 4)}`
        : 'Current season';

    return (
        <GameLayout active="Competitions">
            <Head title={competition?.name ?? 'Competitions'} />
            <header className="border-b border-[#3355dd]/50 bg-black px-6 pt-14 pb-5 text-center">
                <p className="text-xs font-bold tracking-widest text-white/60 uppercase">
                    {seasonLabel}
                    {competition && ` · ${isLeague ? 'League' : 'Cup'}`}
                </p>
                <h1 className="mt-2 text-3xl font-black text-[#3355dd]">
                    {competition?.name ?? 'Competitions'}
                </h1>
                <div className="mx-auto mt-4 flex max-w-xl items-center gap-3">
                    <label
                        htmlFor="competition"
                        className="text-sm font-bold text-[#f5f000]"
                    >
                        Competition
                    </label>
                    <select
                        id="competition"
                        value={competition?.id ?? ''}
                        disabled={competitions.length === 0}
                        onChange={(event) =>
                            router.visit(
                                show.url(Number(event.target.value), {
                                    query: { season_id: season?.id },
                                }),
                            )
                        }
                        className="min-w-0 flex-1 border border-[#3355dd] bg-[#101358] px-3 py-2 text-sm text-white"
                    >
                        {competitions.length === 0 && (
                            <option value="">No competitions available</option>
                        )}
                        {competitions.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ·{' '}
                                {item.type === 'league' ? 'League' : 'Cup'} ·{' '}
                                {item.competition_scope === 'continental'
                                    ? 'Continental'
                                    : item.country_code}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="mx-auto mt-3 flex max-w-xl items-center gap-3">
                    <label
                        htmlFor="season"
                        className="text-sm font-bold text-[#f5f000]"
                    >
                        Season
                    </label>
                    <select
                        id="season"
                        value={season?.id ?? ''}
                        disabled={!competition}
                        onChange={(event) => {
                            if (competition)
                                router.visit(
                                    show.url(competition.id, {
                                        query: {
                                            season_id: Number(
                                                event.target.value,
                                            ),
                                        },
                                    }),
                                );
                        }}
                        className="min-w-0 flex-1 border border-[#3355dd] bg-[#101358] px-3 py-2 text-sm text-white"
                    >
                        {seasons.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.start_date.slice(0, 4)}/
                                {item.end_date.slice(2, 4)}
                                {item.id === currentSeasonId
                                    ? ' · Current season'
                                    : ''}
                            </option>
                        ))}
                    </select>
                </div>
                {historical && (
                    <p className="mt-3 text-xs text-white/60">
                        Season archive
                    </p>
                )}
            </header>

            <main className="flex-1 bg-[#0c0c14] text-white">
                {!competition ? (
                    <p className="p-8 text-center text-white/70">
                        There are no competitions in this game yet.
                    </p>
                ) : (
                    <>
                        <nav
                            aria-label="Competition views"
                            className="flex bg-[#200061]"
                        >
                            {tabs.map((tab) => (
                                <button
                                    key={tab}
                                    type="button"
                                    aria-pressed={activeTab === tab}
                                    onClick={() => setActiveTab(tab)}
                                    className={`flex-1 border-r border-white/10 px-4 py-4 text-sm font-bold ${activeTab === tab ? 'bg-[#1a0050] text-[#f5f000] ring-2 ring-inset ring-[#f5f000]' : 'hover:bg-white/5'}`}
                                >
                                    {tab}
                                </button>
                            ))}
                        </nav>

                        {activeTab === 'Table' && (
                            <StandingsTable
                                rows={table}
                                clubId={clubId}
                                provisional={!historical}
                            />
                        )}
                        {activeTab === 'Groups' &&
                            (groups.length === 0 ? (
                                <p className="p-8 text-center text-white/70">
                                    The group draw has not been made yet.
                                </p>
                            ) : (
                                <div className="grid gap-5 p-4 xl:grid-cols-2">
                                    {groups.map((group) => (
                                        <section
                                            key={group.id}
                                            aria-label={`Group ${group.id}`}
                                            className="min-w-0 overflow-hidden border border-[#3355dd]/50"
                                        >
                                            <h2 className="bg-[#101358] px-4 py-3 font-bold text-[#f5f000]">
                                                Group {group.id}
                                            </h2>
                                            <StandingsTable
                                                rows={group.table}
                                                clubId={clubId}
                                                provisional={!historical}
                                            />
                                        </section>
                                    ))}
                                </div>
                            ))}
                        {activeTab === 'Rounds' && (
                            <KnockoutRounds
                                rounds={rounds}
                                matches={matches}
                                clubId={clubId}
                            />
                        )}
                        {(activeTab === 'Results' ||
                            activeTab === 'Fixtures') && (
                            <>
                                <div className="flex flex-wrap items-center gap-5 border-b border-[#3355dd]/30 px-4 py-3 text-sm">
                                    {!isLeague && (
                                        <label className="flex items-center gap-3">
                                            Stage
                                            <select
                                                value={stageFilter}
                                                onChange={(event) =>
                                                    setStageFilter(
                                                        event.target.value,
                                                    )
                                                }
                                                className="border border-[#3355dd] bg-[#101358] px-3 py-2 text-white"
                                            >
                                                <option value="all">
                                                    All stages
                                                </option>
                                                {groups.map((group) => (
                                                    <option
                                                        key={`group:${group.id}`}
                                                        value={`group:${group.id}`}
                                                    >
                                                        Group {group.id}
                                                    </option>
                                                ))}
                                                {roundNumbers.map((number) => (
                                                    <option
                                                        key={`round:${number}`}
                                                        value={`round:${number}`}
                                                    >
                                                        {roundName(
                                                            rounds.find(
                                                                (round) =>
                                                                    round.round_number ===
                                                                    number,
                                                            )?.name ??
                                                                `Round ${number}`,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                    )}
                                    {clubId !== null && (
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={clubOnly}
                                                onChange={(event) =>
                                                    setClubOnly(
                                                        event.target.checked,
                                                    )
                                                }
                                                className="accent-[#f5f000]"
                                            />
                                            My club only
                                        </label>
                                    )}
                                    <span className="ml-auto text-white/60">
                                        {activeTab === 'Results'
                                            ? results.length
                                            : fixtures.length}{' '}
                                        matches
                                    </span>
                                </div>
                                <MatchList
                                    matches={
                                        activeTab === 'Results'
                                            ? results
                                            : fixtures
                                    }
                                    clubId={clubId}
                                    emptyMessage={
                                        activeTab === 'Results'
                                            ? 'No results yet for this selection.'
                                            : 'No upcoming fixtures for this selection.'
                                    }
                                />
                            </>
                        )}
                    </>
                )}
            </main>
        </GameLayout>
    );
}
