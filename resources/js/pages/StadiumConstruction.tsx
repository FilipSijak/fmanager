import { Head } from '@inertiajs/react';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Minus, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import api from '@/api';
import {
    build as buildConstruction,
    buildableCommercialCategories as fetchBuildableCategories,
    demolishCommercialVenue,
    show as showStadium,
    standExpansionCost,
} from '@/actions/App/Http/Controllers/StadiumController';
import { show as showFinance } from '@/actions/App/Http/Controllers/FinanceController';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

const monoFont = { fontFamily: "'Courier New', ui-monospace, monospace" };

const CAPACITY_STEP = 1000;
const DEFAULT_MORTGAGE_YEARS = 2;

const STAND_LABELS: Record<string, string> = {
    north: 'North Stand',
    east: 'East Stand',
    south: 'South Stand',
    west: 'West Stand',
    north_east: 'North-East Corner',
    south_east: 'South-East Corner',
    south_west: 'South-West Corner',
    north_west: 'North-West Corner',
};

const SIZE_OPTIONS: { value: 1 | 2 | 3; key: 'small' | 'medium' | 'large'; label: string }[] = [
    { value: 1, key: 'small', label: 'Small' },
    { value: 2, key: 'medium', label: 'Medium' },
    { value: 3, key: 'large', label: 'Large' },
];

type PaymentMethod = 'cash' | 'mortgage';

type StadiumStandConstructionData = {
    id: number;
    target_capacity: number;
    capacity_increase: number;
    started_at: string;
    completes_at: string;
} | null;

type StadiumStandData = {
    id: number;
    position: string;
    capacity: number;
    status: string;
    construction: StadiumStandConstructionData;
};

type StadiumCommercialVenueData = {
    id: number;
    size: 1 | 2 | 3;
    build_cost: number;
    category: { id: number; slug: string; name: string; description: string | null } | null;
};

type StadiumData = {
    id: number;
    name: string;
    type: string;
    commercial_limit: number;
    capacity: number;
    active_capacity: number;
    stands: StadiumStandData[];
    commercial_venues: StadiumCommercialVenueData[];
};

type BuildableCategory = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    costs: Partial<Record<'small' | 'medium' | 'large', number>>;
};

type ExpansionPreview = {
    capacity_increase: number;
    maximum_capacity: number;
    cost: number;
    duration_weeks: number;
};

function formatMoney(value: number): string {
    return `£${Math.round(value).toLocaleString('en-US')}`;
}

function formatNumber(value: number): string {
    return value.toLocaleString('en-US');
}

function extractErrorMessage(error: unknown): string {
    if (axios.isAxiosError(error) && typeof error.response?.data?.error === 'string') {
        return error.response.data.error;
    }

    return 'Something went wrong. Please try again.';
}

function sizeLabel(size: 1 | 2 | 3): string {
    return SIZE_OPTIONS.find((option) => option.value === size)?.label ?? 'Unknown';
}

function availableSizesForCategory(category: BuildableCategory) {
    return SIZE_OPTIONS.filter((option) => category.costs[option.key] !== undefined);
}

function ToggleButton({
    label,
    active,
    onClick,
    disabled,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={`w-full cursor-pointer border px-4 py-1.5 text-sm font-bold tracking-wide uppercase transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
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
    disableDecrease,
    disableIncrease,
}: {
    label: string;
    value: string;
    onDecrease: () => void;
    onIncrease: () => void;
    disableDecrease?: boolean;
    disableIncrease?: boolean;
}) {
    return (
        <div className="flex items-center gap-3">
            <button
                type="button"
                aria-label={`Decrease ${label}`}
                onClick={onDecrease}
                disabled={disableDecrease}
                className="flex size-7 shrink-0 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f] disabled:cursor-not-allowed disabled:opacity-40"
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
                disabled={disableIncrease}
                className="flex size-7 shrink-0 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f] disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Plus size={14} />
            </button>
        </div>
    );
}

function PaymentMethodFields({
    paymentMethod,
    lengthYears,
    onPaymentMethodChange,
    onLengthYearsChange,
}: {
    paymentMethod: PaymentMethod;
    lengthYears: number;
    onPaymentMethodChange: (method: PaymentMethod) => void;
    onLengthYearsChange: (years: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3 border-t border-[#1f3a1f] pt-4">
            <div className="grid grid-cols-2 gap-2">
                <ToggleButton
                    label="Cash"
                    active={paymentMethod === 'cash'}
                    onClick={() => onPaymentMethodChange('cash')}
                />
                <ToggleButton
                    label="Mortgage"
                    active={paymentMethod === 'mortgage'}
                    onClick={() => onPaymentMethodChange('mortgage')}
                />
            </div>
            {paymentMethod === 'mortgage' && (
                <StepperRow
                    label="mortgage length"
                    value={`${lengthYears} ${lengthYears === 1 ? 'year' : 'years'}`}
                    onDecrease={() => onLengthYearsChange(Math.max(2, lengthYears - 1))}
                    onIncrease={() => onLengthYearsChange(Math.min(12, lengthYears + 1))}
                />
            )}
        </div>
    );
}

function BuiltVenueRow({
    venue,
    onDemolish,
    isDemolishing,
}: {
    venue: StadiumCommercialVenueData;
    onDemolish: () => void;
    isDemolishing: boolean;
}) {
    const [confirming, setConfirming] = useState(false);

    return (
        <div className="flex items-center justify-between gap-4 border-b border-[#132a13] px-2 py-2 text-xs last:border-b-0">
            <div className="min-w-0">
                <p className="truncate font-bold tracking-wide text-[#c8ffb0] uppercase">
                    {venue.category?.name ?? 'Unknown venue'}
                </p>
                <p className="truncate text-[#5fae5f]">
                    {sizeLabel(venue.size)} · Built for {formatMoney(venue.build_cost)}
                </p>
            </div>
            <button
                type="button"
                disabled={isDemolishing}
                onClick={() => {
                    if (confirming) {
                        onDemolish();
                        setConfirming(false);
                    } else {
                        setConfirming(true);
                    }
                }}
                onBlur={() => setConfirming(false)}
                className="shrink-0 cursor-pointer border border-[#5a1f1f] bg-[#200808] px-2 py-1 text-[10px] font-bold tracking-wide text-[#ff6b6b] uppercase hover:bg-[#3a1010] disabled:cursor-not-allowed disabled:opacity-40"
            >
                {isDemolishing ? 'Demolishing...' : confirming ? 'Confirm?' : 'Demolish'}
            </button>
        </div>
    );
}

function BuildVenueModal({
    categories,
    index,
    selectedSize,
    paymentMethod,
    lengthYears,
    isSubmitting,
    error,
    onNavigate,
    onSelectSize,
    onPaymentMethodChange,
    onLengthYearsChange,
    onBuild,
    onClose,
}: {
    categories: BuildableCategory[] | null;
    index: number;
    selectedSize: 1 | 2 | 3 | null;
    paymentMethod: PaymentMethod;
    lengthYears: number;
    isSubmitting: boolean;
    error: string | null;
    onNavigate: (direction: -1 | 1) => void;
    onSelectSize: (size: 1 | 2 | 3) => void;
    onPaymentMethodChange: (method: PaymentMethod) => void;
    onLengthYearsChange: (years: number) => void;
    onBuild: () => void;
    onClose: () => void;
}) {
    const category = categories?.[index] ?? null;
    const availableSizes = category ? availableSizesForCategory(category) : [];
    const cost = category && selectedSize ? category.costs[SIZE_OPTIONS.find((o) => o.value === selectedSize)!.key] : undefined;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6">
            <div
                className="relative w-full max-w-sm border-2 border-[#39ff14]/50 bg-[#04120a] shadow-[0_0_30px_rgba(57,255,20,0.2)]"
                style={monoFont}
            >
                <div className="flex items-center justify-between border-b border-[#1f3a1f] bg-[#08210f] px-5 py-3">
                    <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                        Build New Venue
                    </span>
                    <button
                        type="button"
                        aria-label="Close"
                        onClick={onClose}
                        className="flex size-7 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#c8ffb0] hover:bg-[#0f2a0f]"
                    >
                        <X size={14} />
                    </button>
                </div>

                {categories === null ? (
                    <p className="p-6 text-center text-xs text-[#5fae5f]">
                        Loading available venues...
                    </p>
                ) : category ? (
                    <div className="flex flex-col gap-4 p-6">
                        <div className="flex items-center justify-center gap-4">
                            <button
                                type="button"
                                aria-label="Previous venue"
                                onClick={() => onNavigate(-1)}
                                className="flex size-8 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
                            >
                                <ChevronLeft size={18} />
                            </button>
                            <p className="min-w-[160px] text-center text-lg font-bold tracking-wide text-[#39ff14] uppercase [text-shadow:0_0_6px_rgba(57,255,20,0.6)]">
                                {category.name}
                            </p>
                            <button
                                type="button"
                                aria-label="Next venue"
                                onClick={() => onNavigate(1)}
                                className="flex size-8 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
                            >
                                <ChevronRight size={18} />
                            </button>
                        </div>

                        {category.description && (
                            <p className="text-center text-xs text-[#5fae5f]">
                                {category.description}
                            </p>
                        )}

                        <div className="grid grid-cols-3 gap-2">
                            {availableSizes.map((option) => (
                                <ToggleButton
                                    key={option.value}
                                    label={option.label}
                                    active={selectedSize === option.value}
                                    onClick={() => onSelectSize(option.value)}
                                />
                            ))}
                        </div>

                        <div className="flex flex-col gap-1 border-t border-[#1f3a1f] pt-4 text-sm">
                            <div className="flex justify-between">
                                <span className="text-[#5fae5f]">
                                    Build Cost
                                </span>
                                <span className="font-bold text-[#c8ffb0]">
                                    {cost !== undefined ? formatMoney(cost) : '—'}
                                </span>
                            </div>
                        </div>

                        <PaymentMethodFields
                            paymentMethod={paymentMethod}
                            lengthYears={lengthYears}
                            onPaymentMethodChange={onPaymentMethodChange}
                            onLengthYearsChange={onLengthYearsChange}
                        />

                        {error && (
                            <p className="text-center text-xs font-bold text-[#ff6b6b]">
                                {error}
                            </p>
                        )}

                        <button
                            type="button"
                            onClick={onBuild}
                            disabled={isSubmitting || selectedSize === null}
                            className="w-full cursor-pointer border border-[#39ff14]/60 bg-[#0f2a0f] py-2 text-sm font-bold tracking-wide text-[#39ff14] uppercase hover:bg-[#153a15] disabled:cursor-not-allowed disabled:opacity-40 [text-shadow:0_0_5px_rgba(57,255,20,0.6)]"
                        >
                            {isSubmitting ? 'Building...' : `Build ${category.name}`}
                        </button>
                    </div>
                ) : (
                    <p className="p-6 text-center text-xs text-[#5fae5f]">
                        All available venues have been built.
                    </p>
                )}
            </div>
        </div>
    );
}

export default function StadiumConstruction() {
    const [stadium, setStadium] = useState<StadiumData | null>(null);
    const [cashAvailable, setCashAvailable] = useState<number | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    const [activeIndex, setActiveIndex] = useState(0);
    const [targetCapacity, setTargetCapacity] = useState(0);
    const [expansionPreview, setExpansionPreview] = useState<ExpansionPreview | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [standPaymentMethod, setStandPaymentMethod] = useState<PaymentMethod>('cash');
    const [standLengthYears, setStandLengthYears] = useState(DEFAULT_MORTGAGE_YEARS);
    const [standError, setStandError] = useState<string | null>(null);
    const [isSubmittingStand, setIsSubmittingStand] = useState(false);

    const [buildableCategories, setBuildableCategories] = useState<BuildableCategory[] | null>(null);
    const [isBuildModalOpen, setIsBuildModalOpen] = useState(false);
    const [buildModalIndex, setBuildModalIndex] = useState(0);
    const [selectedSize, setSelectedSize] = useState<1 | 2 | 3 | null>(null);
    const [venuePaymentMethod, setVenuePaymentMethod] = useState<PaymentMethod>('cash');
    const [venueLengthYears, setVenueLengthYears] = useState(DEFAULT_MORTGAGE_YEARS);
    const [venueError, setVenueError] = useState<string | null>(null);
    const [isSubmittingVenue, setIsSubmittingVenue] = useState(false);

    const [demolishingVenueId, setDemolishingVenueId] = useState<number | null>(null);

    function loadStadium() {
        return api.get(showStadium.url()).then((response) => {
            setStadium(response.data.data as StadiumData);
        });
    }

    function loadBuildableCategories() {
        return api.get(fetchBuildableCategories.url()).then((response) => {
            setBuildableCategories(response.data.data as BuildableCategory[]);
        });
    }

    useEffect(() => {
        loadStadium().catch(() => setLoadError('Unable to load the stadium.'));
        api.get(showFinance.url()).then((response) => {
            setCashAvailable(response.data.data?.balance ?? null);
        });
    }, []);

    const stand = stadium?.stands[activeIndex] ?? null;

    useEffect(() => {
        if (stand) {
            setTargetCapacity(stand.capacity);
            setStandError(null);
        }
    }, [stand?.id]);

    useEffect(() => {
        if (!stand || stand.status === 'under_construction') {
            setExpansionPreview(null);
            return;
        }

        let cancelled = false;
        setPreviewLoading(true);

        api.get(standExpansionCost.url(stand.id, { query: { target_capacity: targetCapacity } }))
            .then((response) => {
                if (!cancelled) {
                    setExpansionPreview(response.data.data as ExpansionPreview);
                    setStandError(null);
                }
            })
            .catch((error) => {
                if (!cancelled) {
                    setExpansionPreview(null);
                    setStandError(extractErrorMessage(error));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setPreviewLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [stand?.id, stand?.status, targetCapacity]);

    function goToStand(direction: -1 | 1) {
        if (!stadium || stadium.stands.length === 0) {
            return;
        }
        setActiveIndex((current) => (current + direction + stadium.stands.length) % stadium.stands.length);
    }

    function submitStandExpansion() {
        if (!stand) {
            return;
        }

        setIsSubmittingStand(true);
        setStandError(null);

        api.post(buildConstruction.url(), {
            building_type: 'stand',
            stand_id: stand.id,
            target_capacity: targetCapacity,
            payment_method: standPaymentMethod,
            length_years: standPaymentMethod === 'mortgage' ? standLengthYears : 0,
        })
            .then(() => loadStadium())
            .catch((error) => setStandError(extractErrorMessage(error)))
            .finally(() => setIsSubmittingStand(false));
    }

    function openBuildModal() {
        setIsBuildModalOpen(true);
        setBuildModalIndex(0);
        setVenueError(null);
        if (buildableCategories === null) {
            loadBuildableCategories();
        }
    }

    function navigateBuildModal(direction: -1 | 1) {
        setBuildModalIndex((current) => {
            const length = buildableCategories?.length ?? 0;
            if (length === 0) {
                return 0;
            }
            return (current + direction + length) % length;
        });
    }

    useEffect(() => {
        const category = buildableCategories?.[buildModalIndex] ?? null;
        setSelectedSize(category ? availableSizesForCategory(category)[0]?.value ?? null : null);
    }, [buildableCategories, buildModalIndex]);

    function submitVenueBuild() {
        const category = buildableCategories?.[buildModalIndex];
        if (!category || selectedSize === null) {
            return;
        }

        setIsSubmittingVenue(true);
        setVenueError(null);

        api.post(buildConstruction.url(), {
            building_type: 'commercial_venue',
            category_id: category.id,
            size: selectedSize,
            payment_method: venuePaymentMethod,
            length_years: venuePaymentMethod === 'mortgage' ? venueLengthYears : 0,
        })
            .then(() => Promise.all([loadStadium(), loadBuildableCategories()]))
            .then(() => setBuildModalIndex(0))
            .catch((error) => setVenueError(extractErrorMessage(error)))
            .finally(() => setIsSubmittingVenue(false));
    }

    function demolishVenue(venueId: number) {
        setDemolishingVenueId(venueId);
        api.delete(demolishCommercialVenue.url(venueId))
            .then(() => Promise.all([loadStadium(), loadBuildableCategories()]))
            .catch((error) => setLoadError(extractErrorMessage(error)))
            .finally(() => setDemolishingVenueId(null));
    }

    if (loadError && !stadium) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="AC Milan - Stadium Construction" />
                <main className="relative flex min-h-screen flex-1 flex-col items-center justify-center bg-black p-6 text-[#c8ffb0]" style={monoFont}>
                    <StadiumSubPageHeader />
                    <p className="text-sm font-bold text-[#ff6b6b]">{loadError}</p>
                </main>
            </GameLayout>
        );
    }

    if (!stadium || !stand) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="AC Milan - Stadium Construction" />
                <main className="relative flex min-h-screen flex-1 flex-col items-center justify-center bg-black p-6 text-[#c8ffb0]" style={monoFont}>
                    <StadiumSubPageHeader />
                    <p className="text-xs tracking-widest text-[#5fae5f] uppercase">Loading stadium...</p>
                </main>
            </GameLayout>
        );
    }

    const underConstruction = stand.status === 'under_construction';
    const capacityIncrease = targetCapacity - stand.capacity;
    const maximumCapacity = expansionPreview?.maximum_capacity ?? targetCapacity;

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

                <header className="relative mb-6 text-center">
                    <p className="text-xs tracking-[0.3em] text-[#5fae5f] uppercase">
                        AC Milan
                    </p>
                    <h1 className="mt-1 text-2xl font-bold tracking-widest text-[#39ff14] uppercase [text-shadow:0_0_8px_rgba(57,255,20,0.7)]">
                        Stadium Construction
                    </h1>
                </header>

                <div className="relative w-full max-w-4xl border-2 border-[#f5f000]/50 bg-[#04120a] shadow-[0_0_20px_rgba(245,240,0,0.15)]">
                    <div className="flex items-center justify-between border-b border-[#f5f000]/50 bg-[#241f08] px-5 py-3 sm:px-8">
                        <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                            Stadium Capacity
                        </span>
                        <span className="text-right">
                            <span className="block text-xl font-bold text-[#f5f000] [text-shadow:0_0_5px_rgba(245,240,0,0.5)]">
                                {formatNumber(stadium.capacity)}
                            </span>
                            <span className="block text-[10px] tracking-widest text-[#5fae5f] uppercase">
                                {formatNumber(stadium.active_capacity)} active
                            </span>
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
                                    {STAND_LABELS[stand.position] ?? stand.position}
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

                            {underConstruction && stand.construction ? (
                                <div className="flex flex-col gap-2 border border-dashed border-[#f5f000]/40 bg-[#1a1608] p-4 text-sm">
                                    <p className="text-xs font-bold tracking-widest text-[#f5f000] uppercase">
                                        Under Construction
                                    </p>
                                    <div className="flex justify-between">
                                        <span className="text-[#5fae5f]">Target Capacity</span>
                                        <span className="font-bold text-[#c8ffb0]">
                                            {formatNumber(stand.construction.target_capacity)} seats
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-[#5fae5f]">Completes</span>
                                        <span className="font-bold text-[#c8ffb0]">
                                            {stand.construction.completes_at}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <StepperRow
                                        label="capacity"
                                        value={`${formatNumber(targetCapacity)} seats`}
                                        disableDecrease={targetCapacity - CAPACITY_STEP < stand.capacity}
                                        disableIncrease={targetCapacity + CAPACITY_STEP > maximumCapacity}
                                        onDecrease={() =>
                                            setTargetCapacity((current) =>
                                                Math.max(stand.capacity, current - CAPACITY_STEP),
                                            )
                                        }
                                        onIncrease={() =>
                                            setTargetCapacity((current) =>
                                                Math.min(maximumCapacity, current + CAPACITY_STEP),
                                            )
                                        }
                                    />

                                    <div className="flex flex-col gap-2 border-t border-[#1f3a1f] pt-4 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-[#5fae5f]">
                                                Expansion Cost
                                            </span>
                                            <span className="font-bold text-[#c8ffb0]">
                                                {previewLoading ? '...' : formatMoney(expansionPreview?.cost ?? 0)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-[#5fae5f]">
                                                Cash Available
                                            </span>
                                            <span className="font-bold text-[#c8ffb0]">
                                                {cashAvailable === null ? '—' : formatMoney(cashAvailable)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-[#5fae5f]">
                                                Construction Time
                                            </span>
                                            <span className="font-bold text-[#c8ffb0]">
                                                {previewLoading ? '...' : `${expansionPreview?.duration_weeks ?? 0} week${expansionPreview?.duration_weeks === 1 ? '' : 's'}`}
                                            </span>
                                        </div>
                                    </div>

                                    {capacityIncrease > 0 && (
                                        <PaymentMethodFields
                                            paymentMethod={standPaymentMethod}
                                            lengthYears={standLengthYears}
                                            onPaymentMethodChange={setStandPaymentMethod}
                                            onLengthYearsChange={setStandLengthYears}
                                        />
                                    )}

                                    {standError && (
                                        <p className="text-xs font-bold text-[#ff6b6b]">
                                            {standError}
                                        </p>
                                    )}

                                    <button
                                        type="button"
                                        onClick={submitStandExpansion}
                                        disabled={capacityIncrease <= 0 || isSubmittingStand || previewLoading}
                                        className="w-full cursor-pointer border border-[#39ff14]/60 bg-[#0f2a0f] py-2 text-sm font-bold tracking-wide text-[#39ff14] uppercase hover:bg-[#153a15] disabled:cursor-not-allowed disabled:opacity-40 [text-shadow:0_0_5px_rgba(57,255,20,0.6)]"
                                    >
                                        {isSubmittingStand ? 'Expanding...' : 'Expand Stand'}
                                    </button>
                                </>
                            )}
                        </div>

                        <div className="flex min-h-[260px] flex-col items-center justify-center gap-2 border border-dashed border-[#1f3a1f] bg-[#020a05] text-center">
                            <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                                {STAND_LABELS[stand.position] ?? stand.position}
                            </span>
                            <span className="text-xs text-[#3a5a3a]">
                                Stand visual coming soon
                            </span>
                        </div>
                    </div>
                </div>

                <div className="relative mt-6 w-full max-w-4xl border-2 border-[#f5f000]/50 bg-[#04120a] shadow-[0_0_20px_rgba(245,240,0,0.15)]">
                    <div className="flex items-center justify-between border-b border-[#f5f000]/50 bg-[#241f08] px-5 py-3 sm:px-8">
                        <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                            Commercial Venues
                        </span>
                        <span className="text-xs font-bold text-[#5fae5f]">
                            {stadium.commercial_venues.length}/{stadium.commercial_limit}
                        </span>
                    </div>

                    <div className="p-5 sm:p-8">
                        {stadium.commercial_venues.length === 0 ? (
                            <p className="text-center text-xs text-[#5fae5f]">
                                No commercial venues have been built yet.
                            </p>
                        ) : (
                            stadium.commercial_venues.map((venue) => (
                                <BuiltVenueRow
                                    key={venue.id}
                                    venue={venue}
                                    isDemolishing={demolishingVenueId === venue.id}
                                    onDemolish={() => demolishVenue(venue.id)}
                                />
                            ))
                        )}
                    </div>
                </div>

                <button
                    type="button"
                    onClick={openBuildModal}
                    className="mt-6 flex w-full max-w-4xl cursor-pointer items-center justify-center gap-2 border border-dashed border-[#1f3a1f] bg-[#04120a] px-5 py-3 text-xs font-bold tracking-widest text-[#5fae5f] uppercase hover:border-[#39ff14]/50 hover:text-[#39ff14]"
                >
                    <Plus size={14} />
                    Build New Venue
                </button>

                {isBuildModalOpen && (
                    <BuildVenueModal
                        categories={buildableCategories}
                        index={Math.min(buildModalIndex, Math.max(0, (buildableCategories?.length ?? 1) - 1))}
                        selectedSize={selectedSize}
                        paymentMethod={venuePaymentMethod}
                        lengthYears={venueLengthYears}
                        isSubmitting={isSubmittingVenue}
                        error={venueError}
                        onNavigate={navigateBuildModal}
                        onSelectSize={setSelectedSize}
                        onPaymentMethodChange={setVenuePaymentMethod}
                        onLengthYearsChange={setVenueLengthYears}
                        onBuild={submitVenueBuild}
                        onClose={() => setIsBuildModalOpen(false)}
                    />
                )}
            </main>
        </GameLayout>
    );
}
