import type { LiveMatchData, MatchEvent, TeamTotals } from '../types';

const EVENT_LABELS: Record<MatchEvent['type'], string> = {
    goal: 'Goal',
    yellow_card: 'Yellow card',
    red_card: 'Red card',
    substitution: 'Substitution',
};

function percentage(part: number, whole: number): string {
    return whole > 0 ? `${Math.round((100 * part) / whole)}%` : '–';
}

/** Each row shows the home value, the stat and the away value, like a TV stats panel. */
const TOTALS: { label: string; value: (totals: TeamTotals) => string }[] = [
    { label: 'Possession', value: (totals) => `${totals.possession}%` },
    { label: 'Chances', value: (totals) => String(totals.chances) },
    {
        label: 'Shots on target',
        value: (totals) => String(totals.shots_on_target),
    },
    { label: 'Key passes', value: (totals) => String(totals.key_passes) },
    {
        label: 'Passes completed',
        value: (totals) =>
            `${totals.passes_completed}/${totals.passes_attempted} (${percentage(totals.passes_completed, totals.passes_attempted)})`,
    },
    {
        label: 'Tackles won',
        value: (totals) => `${totals.tackles_won}/${totals.tackles_attempted}`,
    },
    { label: 'Interceptions', value: (totals) => String(totals.interceptions) },
    { label: 'Fouls', value: (totals) => String(totals.fouls) },
    { label: 'Yellow cards', value: (totals) => String(totals.yellow_cards) },
    { label: 'Red cards', value: (totals) => String(totals.red_cards) },
    { label: 'Offsides', value: (totals) => String(totals.offsides) },
    { label: 'Saves', value: (totals) => String(totals.saves) },
];

function eventDescription(event: MatchEvent): string {
    if (event.type === 'goal') {
        return event.assist_player_name
            ? `${event.player_name} (assist ${event.assist_player_name})`
            : (event.player_name ?? '');
    }

    if (event.type === 'substitution') {
        return `${event.player_name} on for ${event.replaced_player_name}`;
    }

    return event.player_name ?? '';
}

export default function MatchStatsTab({ match }: { match: LiveMatchData }) {
    const events = [...match.events].reverse();

    return (
        <div className="grid gap-6 xl:grid-cols-[3fr_2fr]">
            <section
                className="overflow-hidden border border-[#3355dd]/50"
                aria-label="Team statistics"
            >
                <h2 className="grid grid-cols-[1fr_auto_1fr] gap-4 bg-[#101358] px-5 py-3 font-bold text-[#f5f000]">
                    <span>{match.home.name}</span>
                    <span>Statistics</span>
                    <span className="text-right">{match.away.name}</span>
                </h2>
                <dl className="divide-y divide-white/10">
                    {TOTALS.map((row) => (
                        <div
                            key={row.label}
                            className="grid grid-cols-[1fr_auto_1fr] gap-4 px-5 py-2 tabular-nums"
                        >
                            <dd>{row.value(match.home.totals)}</dd>
                            <dt className="text-center text-[#c8ffb0]">
                                {row.label}
                            </dt>
                            <dd className="text-right">
                                {row.value(match.away.totals)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>
            <section
                className="overflow-hidden border border-[#3355dd]/50"
                aria-label="Match events"
            >
                <h2 className="bg-[#101358] px-5 py-3 font-bold text-[#f5f000]">
                    Match events
                </h2>
                {events.length === 0 ? (
                    <p className="px-5 py-4 text-white/70">
                        {match.minute === 0
                            ? 'The match has not kicked off yet.'
                            : 'Nothing to report yet.'}
                    </p>
                ) : (
                    <ol className="max-h-[480px] divide-y divide-white/10 overflow-y-auto">
                        {events.map((event) => (
                            <li
                                key={`${event.minute}-${event.type}-${event.player_id}-${event.assist_player_name}-${event.replaced_player_name}`}
                                className="flex gap-4 px-5 py-3"
                            >
                                <span className="w-10 font-bold text-[#f5f000] tabular-nums">
                                    {event.minute}′
                                </span>
                                <span className="flex-1">
                                    <span className="font-bold">
                                        {EVENT_LABELS[event.type]}
                                    </span>
                                    {' · '}
                                    {event.team === 'home'
                                        ? match.home.name
                                        : match.away.name}
                                    <span className="block text-sm text-white/70">
                                        {eventDescription(event)}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </section>
        </div>
    );
}
