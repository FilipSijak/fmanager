import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

const monoFont = { fontFamily: "'Courier New', ui-monospace, monospace" };

type StandPlan = {
    id: string;
    label: string;
    capacity: number;
    builtCapacity: number;
    terraces: boolean;
    covered: boolean;
    boxes: number;
    maintenancePercent: number;
    groundCondition: number;
};

const initialStands: StandPlan[] = [
    {
        id: 'north',
        label: 'North Stand',
        capacity: 8000,
        builtCapacity: 8000,
        terraces: false,
        covered: true,
        boxes: 4,
        maintenancePercent: 100,
        groundCondition: 96,
    },
    {
        id: 'north_east',
        label: 'North-East Corner',
        capacity: 2500,
        builtCapacity: 2500,
        terraces: true,
        covered: false,
        boxes: 0,
        maintenancePercent: 100,
        groundCondition: 92,
    },
    {
        id: 'east',
        label: 'East Stand',
        capacity: 9500,
        builtCapacity: 9500,
        terraces: false,
        covered: true,
        boxes: 6,
        maintenancePercent: 100,
        groundCondition: 98,
    },
    {
        id: 'south_east',
        label: 'South-East Corner',
        capacity: 2000,
        builtCapacity: 2000,
        terraces: true,
        covered: false,
        boxes: 0,
        maintenancePercent: 100,
        groundCondition: 90,
    },
    {
        id: 'south',
        label: 'South Stand',
        capacity: 7500,
        builtCapacity: 7500,
        terraces: false,
        covered: true,
        boxes: 2,
        maintenancePercent: 100,
        groundCondition: 94,
    },
    {
        id: 'south_west',
        label: 'South-West Corner',
        capacity: 2000,
        builtCapacity: 2000,
        terraces: true,
        covered: false,
        boxes: 0,
        maintenancePercent: 100,
        groundCondition: 89,
    },
    {
        id: 'west',
        label: 'West Stand',
        capacity: 9000,
        builtCapacity: 9000,
        terraces: false,
        covered: true,
        boxes: 5,
        maintenancePercent: 100,
        groundCondition: 97,
    },
    {
        id: 'north_west',
        label: 'North-West Corner',
        capacity: 2500,
        builtCapacity: 2500,
        terraces: true,
        covered: false,
        boxes: 0,
        maintenancePercent: 100,
        groundCondition: 91,
    },
];

const CASH_AVAILABLE = 1_034_965;
const COST_PER_SEAT = 50;
const WEEKS_PER_1000_SEATS = 2;
const CAPACITY_STEP = 500;

function formatMoney(value: number): string {
    return `£${Math.round(value).toLocaleString('en-US')}`;
}

function formatNumber(value: number): string {
    return value.toLocaleString('en-US');
}

function ToggleButton({
    label,
    active,
    onClick,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`w-full cursor-pointer border px-4 py-1.5 text-sm font-bold tracking-wide uppercase transition-colors ${
                active
                    ? 'border-[#39ff14]/60 bg-[#0f2a0f] text-[#39ff14] [text-shadow:0_0_5px_rgba(57,255,20,0.6)]'
                    : 'border-[#1f3a1f] bg-[#020a05] text-[#5fae5f] hover:border-[#39ff14]/40'
            }`}
        >
            {label}
        </button>
    );
}

function StepperRow({
    label,
    value,
    onDecrease,
    onIncrease,
}: {
    label: string;
    value: string;
    onDecrease: () => void;
    onIncrease: () => void;
}) {
    return (
        <div className="flex items-center gap-3">
            <button
                type="button"
                aria-label={`Decrease ${label}`}
                onClick={onDecrease}
                className="flex size-7 shrink-0 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
            >
                <Minus size={14} />
            </button>
            <span className="min-w-[120px] flex-1 text-center text-sm font-bold text-[#c8ffb0]">
                {value}
            </span>
            <button
                type="button"
                aria-label={`Increase ${label}`}
                onClick={onIncrease}
                className="flex size-7 shrink-0 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
            >
                <Plus size={14} />
            </button>
        </div>
    );
}

export default function StadiumConstruction() {
    const [stands, setStands] = useState<StandPlan[]>(initialStands);
    const [activeIndex, setActiveIndex] = useState(0);

    const stand = stands[activeIndex];
    const totalCapacity = stands.reduce((sum, s) => sum + s.capacity, 0);

    const updateStand = (changes: Partial<StandPlan>) => {
        setStands((current) =>
            current.map((s, i) =>
                i === activeIndex ? { ...s, ...changes } : s,
            ),
        );
    };

    const goToStand = (direction: -1 | 1) => {
        setActiveIndex(
            (current) => (current + direction + stands.length) % stands.length,
        );
    };

    const capacityDelta = stand.capacity - stand.builtCapacity;
    const improvementCost = Math.max(0, capacityDelta) * COST_PER_SEAT;
    const constructionWeeks = Math.ceil(
        (Math.max(0, capacityDelta) / 1000) * WEEKS_PER_1000_SEATS,
    );
    const maintenanceCost = Math.round(
        stand.capacity * 0.1 * (stand.maintenancePercent / 100),
    );

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Stadium Construction" />
            <main
                className="relative flex min-h-screen flex-1 flex-col items-center overflow-hidden bg-black p-6 text-[#c8ffb0] sm:p-10"
                style={monoFont}
            >
                <div
                    className="pointer-events-none absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'repeating-linear-gradient(0deg, rgba(255,255,255,0.15) 0px, rgba(255,255,255,0.15) 1px, transparent 1px, transparent 3px)',
                    }}
                />
                <StadiumSubPageHeader />

                <div className="relative w-full max-w-4xl border border-[#1f3a1f] bg-[#04120a]">
                    <div className="flex items-center justify-between border-b border-[#1f3a1f] bg-[#08210f] px-5 py-3 sm:px-8">
                        <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                            Stadium Capacity
                        </span>
                        <span className="text-xl font-bold text-[#f5f000] [text-shadow:0_0_5px_rgba(245,240,0,0.5)]">
                            {formatNumber(totalCapacity)}
                        </span>
                    </div>

                    <div className="grid grid-cols-1 gap-8 p-5 sm:p-8 md:grid-cols-2">
                        <div className="flex flex-col gap-5">
                            <div className="flex items-center justify-center gap-4">
                                <button
                                    type="button"
                                    aria-label="Previous stand"
                                    onClick={() => goToStand(-1)}
                                    className="flex size-8 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
                                >
                                    <ChevronLeft size={18} />
                                </button>
                                <h1 className="min-w-[220px] text-center text-lg font-bold tracking-wide text-[#39ff14] uppercase [text-shadow:0_0_6px_rgba(57,255,20,0.6)]">
                                    {stand.label}
                                </h1>
                                <button
                                    type="button"
                                    aria-label="Next stand"
                                    onClick={() => goToStand(1)}
                                    className="flex size-8 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
                                >
                                    <ChevronRight size={18} />
                                </button>
                            </div>

                            <StepperRow
                                label="capacity"
                                value={`${formatNumber(stand.capacity)} seats`}
                                onDecrease={() =>
                                    updateStand({
                                        capacity: Math.max(
                                            0,
                                            stand.capacity - CAPACITY_STEP,
                                        ),
                                    })
                                }
                                onIncrease={() =>
                                    updateStand({
                                        capacity:
                                            stand.capacity + CAPACITY_STEP,
                                    })
                                }
                            />

                            <ToggleButton
                                label="Terraces"
                                active={stand.terraces}
                                onClick={() =>
                                    updateStand({ terraces: !stand.terraces })
                                }
                            />
                            <ToggleButton
                                label="Covered"
                                active={stand.covered}
                                onClick={() =>
                                    updateStand({ covered: !stand.covered })
                                }
                            />

                            <StepperRow
                                label="boxes"
                                value={
                                    stand.boxes === 0
                                        ? 'No Boxes'
                                        : `${stand.boxes} Box${stand.boxes === 1 ? '' : 'es'}`
                                }
                                onDecrease={() =>
                                    updateStand({
                                        boxes: Math.max(0, stand.boxes - 1),
                                    })
                                }
                                onIncrease={() =>
                                    updateStand({ boxes: stand.boxes + 1 })
                                }
                            />

                            <div className="flex flex-col gap-2 border-t border-[#1f3a1f] pt-4 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-[#5fae5f]">
                                        Improvement Cost
                                    </span>
                                    <span className="font-bold text-[#c8ffb0]">
                                        {formatMoney(improvementCost)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-[#5fae5f]">
                                        Cash Available
                                    </span>
                                    <span className="font-bold text-[#c8ffb0]">
                                        {formatMoney(CASH_AVAILABLE)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-[#5fae5f]">
                                        Construction Time
                                    </span>
                                    <span className="font-bold text-[#c8ffb0]">
                                        {constructionWeeks} week
                                        {constructionWeeks === 1 ? '' : 's'}
                                    </span>
                                </div>
                            </div>

                            <div className="flex flex-col gap-3 border-t border-[#1f3a1f] pt-4">
                                <StepperRow
                                    label="maintenance"
                                    value={`${stand.maintenancePercent}%`}
                                    onDecrease={() =>
                                        updateStand({
                                            maintenancePercent: Math.max(
                                                0,
                                                stand.maintenancePercent - 5,
                                            ),
                                        })
                                    }
                                    onIncrease={() =>
                                        updateStand({
                                            maintenancePercent:
                                                stand.maintenancePercent + 5,
                                        })
                                    }
                                />
                                <div className="flex justify-between text-sm">
                                    <span className="text-[#5fae5f]">Cost</span>
                                    <span className="font-bold text-[#c8ffb0]">
                                        {formatMoney(maintenanceCost)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-[#5fae5f]">
                                        Ground Condition
                                    </span>
                                    <span className="font-bold text-[#c8ffb0]">
                                        {stand.groundCondition}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="flex min-h-[260px] flex-col items-center justify-center gap-2 border border-dashed border-[#1f3a1f] bg-[#020a05] text-center">
                            <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                                {stand.label}
                            </span>
                            <span className="text-xs text-[#3a5a3a]">
                                Stand visual coming soon
                            </span>
                        </div>
                    </div>
                </div>
            </main>
        </GameLayout>
    );
}
