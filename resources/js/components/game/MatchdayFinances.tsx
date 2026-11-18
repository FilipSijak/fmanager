import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { finance } from '@/routes';
import { show as showMatch } from '@/routes/matches';

export type MatchdaySettlement = {
    game_id: number;
    match_start: string | null;
    opponent_id: number;
    opponent_name: string;
    competition_name: string;
    home_goals: number | null;
    away_goals: number | null;
    attendance: number;
    season_ticket_attendance: number;
    paying_attendance: number | null;
    ticket_type: 'league' | 'cup' | 'continental' | null;
    ticket_price: number;
    gate_receipts: number;
    commercial_income: number;
    operating_costs: number;
    season_ticket_refund_quantity: number;
    season_ticket_refund_amount: number;
    net_income: number;
    settled_on: string;
};

export type MatchdayFinanceOverview = {
    summary: {
        matches: number;
        attendance: number;
        average_attendance: number;
        gate_receipts: number;
        commercial_income: number;
        operating_costs: number;
        season_ticket_refunds: number;
        net_income: number;
        season_ticket_income: number;
        net_including_season_tickets: number;
    };
    season_tickets: {
        quantity: number;
        home_games: number;
        ticket_price: number;
        league_ticket_price: number;
        amount: number;
        sold_on: string;
    } | null;
    matches: MatchdaySettlement[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

const number = new Intl.NumberFormat('en-GB');
const currency = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    maximumFractionDigits: 0,
});
const date = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });

function formatDate(value: string | null) {
    return value
        ? date.format(new Date(`${value.slice(0, 10)}T12:00:00`))
        : 'Not recorded';
}

function Profit({ amount }: { amount: number }) {
    return (
        <span
            className={`font-bold tabular-nums ${amount < 0 ? 'text-red-400' : 'text-[#39ff14]'}`}
        >
            {currency.format(amount)}
        </span>
    );
}

function AmountRow({
    label,
    amount,
    expense = false,
}: {
    label: string;
    amount: number;
    expense?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-2">
            <dt className="text-[#8abb83]">{label}</dt>
            <dd
                className={`shrink-0 tabular-nums ${expense && amount > 0 ? 'text-red-300' : 'text-[#c8ffb0]'}`}
            >
                {expense && amount > 0 ? '−' : ''}
                {currency.format(amount)}
            </dd>
        </div>
    );
}

export function MatchdayBreakdown({
    settlement,
}: {
    settlement: MatchdaySettlement;
}) {
    return (
        <div className="flex flex-col gap-4 text-sm text-[#c8ffb0]">
            <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[
                    ['Attendance', number.format(settlement.attendance)],
                    [
                        'Season-ticket holders',
                        number.format(settlement.season_ticket_attendance),
                    ],
                    [
                        'Match-ticket holders',
                        settlement.paying_attendance === null
                            ? 'Not recorded'
                            : number.format(settlement.paying_attendance),
                    ],
                    [
                        'Match-ticket price',
                        currency.format(settlement.ticket_price),
                    ],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="border border-[#1f3a1f] bg-[#020a05] p-3"
                    >
                        <dt className="text-xs text-[#8abb83]">{label}</dt>
                        <dd className="mt-1 text-lg font-bold text-[#f5f000] tabular-nums">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <h3 className="text-xs font-bold tracking-wider text-[#39ff14] uppercase">
                        Income
                    </h3>
                    <dl className="divide-y divide-[#1f3a1f]">
                        <AmountRow
                            label="Gate receipts"
                            amount={settlement.gate_receipts}
                        />
                        <AmountRow
                            label="Commercial sales"
                            amount={settlement.commercial_income}
                        />
                    </dl>
                </div>
                <div>
                    <h3 className="text-xs font-bold tracking-wider text-[#39ff14] uppercase">
                        Costs & refunds
                    </h3>
                    <dl className="divide-y divide-[#1f3a1f]">
                        <AmountRow
                            label="Operating costs"
                            amount={settlement.operating_costs}
                            expense
                        />
                        <AmountRow
                            label="Construction refunds"
                            amount={settlement.season_ticket_refund_amount}
                            expense
                        />
                    </dl>
                    {settlement.season_ticket_refund_quantity > 0 && (
                        <p className="mt-1 text-xs text-[#8abb83]">
                            {number.format(
                                settlement.season_ticket_refund_quantity,
                            )}{' '}
                            displaced season-ticket holders refunded.
                        </p>
                    )}
                </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-[#1f3a1f] pt-3">
                <p className="font-bold">Net matchday income</p>
                <p className="text-xl">
                    <Profit amount={settlement.net_income} />
                </p>
            </div>
            <p className="text-xs leading-relaxed text-[#8abb83]">
                Season-ticket holders paid upfront. Their admission is included
                in attendance, not gate receipts. Operating costs include
                stadium running costs, venue staffing and stock.
            </p>
        </div>
    );
}

function PageLink({
    page,
    children,
}: {
    page: number | null;
    children: ReactNode;
}) {
    const className = 'border border-[#1f3a1f] px-3 py-2 text-xs font-bold';
    return page === null ? (
        <button type="button" disabled className={`${className} opacity-40`}>
            {children}
        </button>
    ) : (
        <Link
            href={finance.url({ query: { matchday_page: page } })}
            only={['matchdayFinances']}
            preserveState
            preserveScroll
            className={`${className} text-[#f5f000] hover:bg-[#08210f] focus-visible:outline-2 focus-visible:outline-[#f5f000]`}
        >
            {children}
        </Link>
    );
}

export default function MatchdayFinances({
    data,
}: {
    data: MatchdayFinanceOverview;
}) {
    const { summary, season_tickets: sale, matches, pagination } = data;
    return (
        <section
            id="matchday-finances"
            aria-labelledby="matchday-finances-title"
            className="border border-[#1f3a1f] bg-[#04120a] text-[#c8ffb0]"
        >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[#1f3a1f] bg-[#08210f] px-4 py-2">
                <h2
                    id="matchday-finances-title"
                    className="text-sm font-bold tracking-widest text-[#39ff14] uppercase"
                >
                    Matchday finances
                </h2>
                <span className="text-xs text-[#8abb83]">
                    Current season · Home matches
                </span>
            </div>
            <div className="flex flex-col gap-5 p-4">
                <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="border border-[#1f3a1f] bg-[#020a05] p-3">
                        <dt className="text-xs text-[#8abb83]">
                            Net matchday income
                        </dt>
                        <dd className="mt-1 text-xl">
                            <Profit amount={summary.net_income} />
                        </dd>
                    </div>
                    <div className="border border-[#1f3a1f] bg-[#020a05] p-3">
                        <dt className="text-xs text-[#8abb83]">
                            Home matches settled
                        </dt>
                        <dd className="mt-1 text-xl font-bold text-[#f5f000] tabular-nums">
                            {number.format(summary.matches)}
                        </dd>
                    </div>
                    <div className="border border-[#1f3a1f] bg-[#020a05] p-3">
                        <dt className="text-xs text-[#8abb83]">
                            Average attendance
                        </dt>
                        <dd className="mt-1 text-xl font-bold text-[#f5f000] tabular-nums">
                            {summary.matches
                                ? number.format(summary.average_attendance)
                                : '—'}
                        </dd>
                    </div>
                    <div className="border border-[#1f3a1f] bg-[#020a05] p-3">
                        <dt className="text-xs text-[#8abb83]">
                            Season tickets · Upfront
                        </dt>
                        <dd className="mt-1 text-xl font-bold text-[#f5f000] tabular-nums">
                            {currency.format(summary.season_ticket_income)}
                        </dd>
                    </div>
                </dl>
                <div className="grid gap-5 lg:grid-cols-2">
                    <div>
                        <h3 className="text-xs font-bold tracking-wider text-[#39ff14] uppercase">
                            Matchday income & expenses
                        </h3>
                        <dl className="divide-y divide-[#1f3a1f] text-sm">
                            <AmountRow
                                label="Gate receipts"
                                amount={summary.gate_receipts}
                            />
                            <AmountRow
                                label="Commercial sales"
                                amount={summary.commercial_income}
                            />
                            <AmountRow
                                label="Operating costs"
                                amount={summary.operating_costs}
                                expense
                            />
                            <AmountRow
                                label="Construction refunds"
                                amount={summary.season_ticket_refunds}
                                expense
                            />
                        </dl>
                    </div>
                    <div className="border border-[#1f3a1f] bg-[#020a05] p-4">
                        <h3 className="text-xs font-bold tracking-wider text-[#39ff14] uppercase">
                            Season-ticket sales
                        </h3>
                        {sale ? (
                            <>
                                <p className="mt-3 text-sm">
                                    <span className="font-bold text-[#f5f000]">
                                        {number.format(sale.quantity)}
                                    </span>{' '}
                                    passes sold for{' '}
                                    <span className="font-bold">
                                        {currency.format(sale.ticket_price)}
                                    </span>{' '}
                                    each
                                </p>
                                <p className="mt-2 text-xs leading-relaxed text-[#8abb83]">
                                    Covers {number.format(sale.home_games)} home
                                    league matches at{' '}
                                    {currency.format(sale.league_ticket_price)}{' '}
                                    per match. Paid {formatDate(sale.sold_on)}.
                                </p>
                            </>
                        ) : (
                            <p className="mt-3 text-sm text-[#8abb83]">
                                No season-ticket sale recorded for this season.
                            </p>
                        )}
                        <p className="mt-3 text-xs leading-relaxed text-[#8abb83]">
                            Upfront income is separate from gate receipts.
                            Construction refunds are deducted above.
                        </p>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[#1f3a1f] pt-3 text-sm">
                            <span>Net including season tickets</span>
                            <Profit
                                amount={summary.net_including_season_tickets}
                            />
                        </div>
                    </div>
                </div>
                <div>
                    <h3 className="mb-3 text-xs font-bold tracking-wider text-[#39ff14] uppercase">
                        Match history
                    </h3>
                    {matches.length === 0 ? (
                        <div className="border border-dashed border-[#1f3a1f] p-6 text-center text-sm text-[#8abb83]">
                            {pagination.total === 0 ? (
                                <p>
                                    No home-match finances yet. Completed home
                                    matches will appear here.
                                </p>
                            ) : (
                                <p>
                                    No matches on this page.{' '}
                                    <Link
                                        href={finance.url()}
                                        className="text-[#f5f000] underline"
                                    >
                                        Return to the latest matches
                                    </Link>
                                    .
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto border border-[#1f3a1f]">
                            <table className="w-full min-w-[950px] text-left text-xs tabular-nums">
                                <caption className="sr-only">
                                    Home-match income and expenses, newest
                                    first. Amounts in pounds sterling.
                                </caption>
                                <thead className="bg-[#08210f] text-[#8abb83]">
                                    <tr>
                                        {[
                                            'Match',
                                            'Attendance',
                                            'Gate receipts',
                                            'Commercial',
                                            'Operating costs',
                                            'Refunds',
                                            'Net income',
                                        ].map((heading) => (
                                            <th
                                                key={heading}
                                                scope="col"
                                                className="px-3 py-3"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#1f3a1f]">
                                    {matches.map((match) => (
                                        <tr
                                            key={match.game_id}
                                            className="align-top hover:bg-[#08210f]/60"
                                        >
                                            <th
                                                scope="row"
                                                className="min-w-48 px-3 py-3 font-normal"
                                            >
                                                <Link
                                                    href={showMatch.url(
                                                        match.game_id,
                                                    )}
                                                    className="font-bold text-[#f5f000] underline decoration-[#f5f000]/30 underline-offset-4 hover:text-white"
                                                >
                                                    v {match.opponent_name} ·{' '}
                                                    {match.home_goals ?? '—'}–
                                                    {match.away_goals ?? '—'}
                                                </Link>
                                                <p className="mt-2 text-[#8abb83]">
                                                    {formatDate(
                                                        match.match_start,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-[#8abb83]">
                                                    {match.competition_name}
                                                </p>
                                            </th>
                                            <td className="px-3 py-3">
                                                <p className="font-bold">
                                                    {number.format(
                                                        match.attendance,
                                                    )}
                                                </p>
                                                <p className="mt-2 whitespace-nowrap text-[#8abb83]">
                                                    {number.format(
                                                        match.season_ticket_attendance,
                                                    )}{' '}
                                                    season tickets
                                                </p>
                                                <p className="mt-1 whitespace-nowrap text-[#8abb83]">
                                                    {match.paying_attendance ===
                                                    null
                                                        ? 'Match tickets not recorded'
                                                        : `${number.format(match.paying_attendance)} match tickets`}
                                                </p>
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-3">
                                                {currency.format(
                                                    match.gate_receipts,
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-3">
                                                {currency.format(
                                                    match.commercial_income,
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-3">
                                                {currency.format(
                                                    match.operating_costs,
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <p>
                                                    {currency.format(
                                                        match.season_ticket_refund_amount,
                                                    )}
                                                </p>
                                                {match.season_ticket_refund_quantity >
                                                    0 && (
                                                    <p className="mt-2 whitespace-nowrap text-[#8abb83]">
                                                        {number.format(
                                                            match.season_ticket_refund_quantity,
                                                        )}{' '}
                                                        holders
                                                    </p>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-3">
                                                <Profit
                                                    amount={match.net_income}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {pagination.total > 0 && (
                        <nav
                            aria-label="Matchday history pagination"
                            className="mt-3 flex flex-wrap items-center justify-between gap-3"
                        >
                            <p className="text-xs text-[#8abb83]">
                                {pagination.from === null
                                    ? 'No matches'
                                    : `${pagination.from}–${pagination.to}`}{' '}
                                of {number.format(pagination.total)} matches
                            </p>
                            <div className="flex gap-2">
                                <PageLink
                                    page={
                                        pagination.current_page > 1
                                            ? Math.min(
                                                  pagination.current_page - 1,
                                                  pagination.last_page,
                                              )
                                            : null
                                    }
                                >
                                    Previous
                                </PageLink>
                                <PageLink
                                    page={
                                        pagination.current_page <
                                        pagination.last_page
                                            ? pagination.current_page + 1
                                            : null
                                    }
                                >
                                    Next
                                </PageLink>
                            </div>
                        </nav>
                    )}
                </div>
            </div>
        </section>
    );
}
