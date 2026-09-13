import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import GameLayout from '@/layouts/GameLayout';

const monoFont = { fontFamily: "'Courier New', ui-monospace, monospace" };

function formatMoney(value: number): string {
    const sign = value < 0 ? '-' : '';
    return `${sign}£${Math.abs(Math.round(value)).toLocaleString('en-US')}`;
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border border-[#1f3a1f] bg-[#04120a]">
            <header className="border-b border-[#1f3a1f] bg-[#08210f] px-4 py-2">
                <h2 className="text-sm font-bold tracking-[0.2em] text-[#39ff14] uppercase [text-shadow:0_0_6px_rgba(57,255,20,0.6)]">
                    {title}
                </h2>
            </header>
            <div className="p-4">{children}</div>
        </section>
    );
}

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="border border-[#1f3a1f] bg-[#020a05] px-3 py-2">
            <p className="text-[10px] tracking-widest text-[#5fae5f] uppercase">
                {label}
            </p>
            <p className="mt-1 text-lg font-bold text-[#f5f000] [text-shadow:0_0_5px_rgba(245,240,0,0.5)]">
                {value}
            </p>
        </div>
    );
}

function Row({
    label,
    col2 = '',
    col3 = '',
    header = false,
}: {
    label: string;
    col2?: string;
    col3?: string;
    header?: boolean;
}) {
    return (
        <div
            className={`grid grid-cols-4 gap-2 border-b border-[#132a13] px-2 py-1.5 text-xs last:border-b-0 ${
                header
                    ? 'text-[#5fae5f] uppercase tracking-wide'
                    : 'text-[#c8ffb0]'
            }`}
        >
            <span className="col-span-2 truncate">{label}</span>
            <span className="text-right">{col2}</span>
            <span className="text-right">{col3}</span>
        </div>
    );
}

const seasonProgress = [
    { month: 'Aug', balance: 2_100_000 },
    { month: 'Sep', balance: 2_450_000 },
    { month: 'Oct', balance: 2_300_000 },
    { month: 'Nov', balance: 2_900_000 },
    { month: 'Dec', balance: 3_350_000 },
    { month: 'Jan', balance: 2_800_000 },
    { month: 'Feb', balance: 3_100_000 },
    { month: 'Mar', balance: 3_600_000 },
    { month: 'Apr', balance: 3_950_000 },
    { month: 'May', balance: 4_235_000 },
];

function SeasonChart() {
    const width = 760;
    const height = 220;
    const padding = { top: 16, right: 16, bottom: 28, left: 64 };
    const plotW = width - padding.left - padding.right;
    const plotH = height - padding.top - padding.bottom;

    const values = seasonProgress.map((p) => p.balance);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;

    const points = seasonProgress.map((p, i) => {
        const x = padding.left + (i / (seasonProgress.length - 1)) * plotW;
        const y = padding.top + plotH - ((p.balance - min) / range) * plotH;
        return { x, y, ...p };
    });

    const linePath = points
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`)
        .join(' ');

    const gridLines = 4;

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="w-full"
            role="img"
            aria-label="Season balance progress chart"
        >
            {Array.from({ length: gridLines + 1 }).map((_, i) => {
                const y = padding.top + (i / gridLines) * plotH;
                const value = max - (i / gridLines) * range;
                return (
                    // biome-ignore lint/suspicious/noArrayIndexKey: fixed-length gridline set, never reordered
                    <g key={i}>
                        <line
                            x1={padding.left}
                            y1={y}
                            x2={width - padding.right}
                            y2={y}
                            stroke="#1f3a1f"
                            strokeDasharray="2,3"
                        />
                        <text
                            x={padding.left - 8}
                            y={y + 3}
                            textAnchor="end"
                            fontSize="9"
                            fill="#5fae5f"
                            fontFamily="'Courier New', monospace"
                        >
                            £{(value / 1_000_000).toFixed(1)}m
                        </text>
                    </g>
                );
            })}

            {points.map((p) => (
                <text
                    key={`label-${p.month}`}
                    x={p.x}
                    y={height - 8}
                    textAnchor="middle"
                    fontSize="10"
                    fill="#5fae5f"
                    fontFamily="'Courier New', monospace"
                >
                    {p.month}
                </text>
            ))}

            <path
                d={linePath}
                fill="none"
                stroke="#39ff14"
                strokeWidth="2"
                style={{
                    filter: 'drop-shadow(0 0 4px rgba(57,255,20,0.8))',
                }}
            />
            {points.map((p) => (
                <circle
                    key={`dot-${p.month}`}
                    cx={p.x}
                    cy={p.y}
                    r="3"
                    fill="#f5f000"
                    style={{
                        filter: 'drop-shadow(0 0 3px rgba(245,240,0,0.8))',
                    }}
                />
            ))}
        </svg>
    );
}

function LoanRow({
    name,
    rate,
    repayment,
    remaining,
}: {
    name: string;
    rate: string;
    repayment: string;
    remaining: string;
}) {
    return (
        <div className="border-b border-[#132a13] py-2 text-xs last:border-b-0">
            <p className="font-bold text-[#c8ffb0]">{name}</p>
            <div className="mt-1 grid grid-cols-3 gap-2 text-[#5fae5f]">
                <span>Rate: {rate}</span>
                <span>Repayment: {repayment}</span>
                <span>Remaining: {remaining}</span>
            </div>
        </div>
    );
}

const loans = [
    {
        name: 'Stadium Redevelopment Loan',
        rate: '4.5%',
        repayment: '£42,000/mo',
        remaining: '8 years',
    },
    {
        name: 'Training Ground Loan',
        rate: '3.8%',
        repayment: '£15,000/mo',
        remaining: '3 years',
    },
];

const sponsors = [
    { name: 'Volkswagen (Shirt)', value: '£3,200,000/season', expires: '2028' },
    {
        name: 'Allianz (Stadium Naming)',
        value: '£2,000,000/season',
        expires: '2030',
    },
    {
        name: 'Nike (Training Kit)',
        value: '£1,800,000/season',
        expires: '2027',
    },
    { name: 'DHL (Sleeve)', value: '£900,000/season', expires: '2026' },
];

const commercial = [
    { name: 'Media & Broadcasting', value: '£9,500,000/season' },
    { name: 'Matchday Merchandise', value: '£680,000/season' },
    { name: 'Bar & Restaurant Sales', value: '£420,000/season' },
    { name: 'Shop Sales', value: '£310,000/season' },
    { name: 'Corporate Hospitality', value: '£1,150,000/season' },
];

const stadiumFinance = [
    { name: 'Ticket Revenue', value: '£11,200,000/season' },
    { name: 'Average Attendance', value: '38,500' },
    { name: 'Stadium Capacity', value: '44,000' },
    { name: 'Maintenance Costs', value: '£1,850,000/season' },
    { name: 'Loan Repayments', value: '£504,000/season' },
];

export default function Finance() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Finance" />
            <main
                className="relative min-h-screen flex-1 overflow-hidden bg-black p-6 text-[#c8ffb0] sm:p-10"
                style={monoFont}
            >
                <div
                    className="pointer-events-none absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'repeating-linear-gradient(0deg, rgba(255,255,255,0.15) 0px, rgba(255,255,255,0.15) 1px, transparent 1px, transparent 3px)',
                    }}
                />

                <div className="relative mx-auto flex max-w-6xl flex-col gap-6">
                    <header className="text-center">
                        <p className="text-xs tracking-[0.3em] text-[#5fae5f] uppercase">
                            AC Milan
                        </p>
                        <h1 className="mt-1 text-2xl font-bold tracking-widest text-[#39ff14] uppercase [text-shadow:0_0_8px_rgba(57,255,20,0.7)]">
                            Finance Center
                        </h1>
                        <p className="mt-1 text-xs tracking-widest text-[#5fae5f] uppercase">
                            Season 2001/02
                        </p>
                    </header>

                    <Panel title="Overall Finances">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            <StatCard
                                label="Bank Balance"
                                value={formatMoney(4_235_000)}
                            />
                            <StatCard
                                label="Transfer Budget"
                                value={formatMoney(1_500_000)}
                            />
                            <StatCard label="Wage Budget" value="£85,000/wk" />
                            <StatCard
                                label="Season Income"
                                value={formatMoney(28_400_000)}
                            />
                            <StatCard
                                label="Season Expenditure"
                                value={formatMoney(24_150_000)}
                            />
                            <StatCard
                                label="Net Profit"
                                value={formatMoney(4_250_000)}
                            />
                        </div>
                    </Panel>

                    <Panel title="Season Progress">
                        <SeasonChart />
                    </Panel>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Panel title="Loans">
                            {loans.map((loan) => (
                                <LoanRow key={loan.name} {...loan} />
                            ))}
                        </Panel>

                        <Panel title="Sponsors">
                            <Row
                                label="Sponsor"
                                col2="Value"
                                col3="Expires"
                                header
                            />
                            {sponsors.map((sponsor) => (
                                <Row
                                    key={sponsor.name}
                                    label={sponsor.name}
                                    col2={sponsor.value}
                                    col3={sponsor.expires}
                                />
                            ))}
                        </Panel>

                        <Panel title="Commercial">
                            <Row label="Source" col2="Value" header />
                            {commercial.map((item) => (
                                <Row
                                    key={item.name}
                                    label={item.name}
                                    col2={item.value}
                                />
                            ))}
                        </Panel>

                        <Panel title="Stadium">
                            <Row label="Item" col2="Value" header />
                            {stadiumFinance.map((item) => (
                                <Row
                                    key={item.name}
                                    label={item.name}
                                    col2={item.value}
                                />
                            ))}
                        </Panel>
                    </div>
                </div>
            </main>
        </GameLayout>
    );
}
