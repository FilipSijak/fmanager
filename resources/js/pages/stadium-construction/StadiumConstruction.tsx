import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { show as showFinance } from '@/actions/App/Http/Controllers/FinanceController';
import {
    build as buildConstruction,
    demolishCommercialVenue,
    buildableCommercialCategories as fetchBuildableCategories,
    show as showStadium,
    standExpansionCost,
} from '@/actions/App/Http/Controllers/StadiumController';
import api from '@/api';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';
import BuildVenueModal from './components/BuildVenueModal';
import BuiltVenueRow from './components/BuiltVenueRow';
import PaymentMethodFields from './components/PaymentMethodFields';
import StepperRow from './components/StepperRow';
import type {
    BuildableCategory,
    ExpansionPreview,
    PaymentMethod,
    StadiumData,
} from './types';
import {
    availableSizesForCategory,
    CAPACITY_STEP,
    DEFAULT_MORTGAGE_YEARS,
    extractErrorMessage,
    formatMoney,
    formatNumber,
    monoFont,
    STAND_LABELS,
} from './utils';

export default function StadiumConstruction() {
    const [stadium, setStadium] = useState<StadiumData | null>(null);
    const [cashAvailable, setCashAvailable] = useState<number | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    const [activeIndex, setActiveIndex] = useState(0);
    const [targetCapacity, setTargetCapacity] = useState(0);
    const [expansionPreview, setExpansionPreview] =
        useState<ExpansionPreview | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [standPaymentMethod, setStandPaymentMethod] =
        useState<PaymentMethod>('cash');
    const [standLengthYears, setStandLengthYears] = useState(
        DEFAULT_MORTGAGE_YEARS,
    );
    const [standError, setStandError] = useState<string | null>(null);
    const [isSubmittingStand, setIsSubmittingStand] = useState(false);

    const [buildableCategories, setBuildableCategories] = useState<
        BuildableCategory[] | null
    >(null);
    const [isBuildModalOpen, setIsBuildModalOpen] = useState(false);
    const [buildModalIndex, setBuildModalIndex] = useState(0);
    const [selectedSize, setSelectedSize] = useState<1 | 2 | 3 | null>(null);
    const [venuePaymentMethod, setVenuePaymentMethod] =
        useState<PaymentMethod>('cash');
    const [venueLengthYears, setVenueLengthYears] = useState(
        DEFAULT_MORTGAGE_YEARS,
    );
    const [venueError, setVenueError] = useState<string | null>(null);
    const [isSubmittingVenue, setIsSubmittingVenue] = useState(false);

    const [demolishingVenueId, setDemolishingVenueId] = useState<number | null>(
        null,
    );

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
    }, [stand]);

    useEffect(() => {
        if (!stand || stand.status === 'under_construction') {
            setExpansionPreview(null);
            return;
        }

        let cancelled = false;
        setPreviewLoading(true);

        api.get(
            standExpansionCost.url(stand.id, {
                query: { target_capacity: targetCapacity },
            }),
        )
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
    }, [stand, targetCapacity]);

    function goToStand(direction: -1 | 1) {
        if (!stadium || stadium.stands.length === 0) {
            return;
        }
        setActiveIndex(
            (current) =>
                (current + direction + stadium.stands.length) %
                stadium.stands.length,
        );
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
            length_years:
                standPaymentMethod === 'mortgage' ? standLengthYears : 0,
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
        setSelectedSize(
            category
                ? (availableSizesForCategory(category)[0]?.value ?? null)
                : null,
        );
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
            length_years:
                venuePaymentMethod === 'mortgage' ? venueLengthYears : 0,
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
                <main
                    className="relative flex min-h-screen flex-1 flex-col items-center justify-center bg-black p-6 text-[#c8ffb0]"
                    style={monoFont}
                >
                    <StadiumSubPageHeader />
                    <p className="text-sm font-bold text-[#ff6b6b]">
                        {loadError}
                    </p>
                </main>
            </GameLayout>
        );
    }

    if (!stadium || !stand) {
        return (
            <GameLayout active="Nations & Clubs">
                <Head title="AC Milan - Stadium Construction" />
                <main
                    className="relative flex min-h-screen flex-1 flex-col items-center justify-center bg-black p-6 text-[#c8ffb0]"
                    style={monoFont}
                >
                    <StadiumSubPageHeader />
                    <p className="text-xs tracking-widest text-[#5fae5f] uppercase">
                        Loading stadium...
                    </p>
                </main>
            </GameLayout>
        );
    }

    const underConstruction = stand.status === 'under_construction';
    const capacityIncrease = targetCapacity - stand.capacity;
    const maximumCapacity =
        expansionPreview?.maximum_capacity ?? targetCapacity;

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
                                    {STAND_LABELS[stand.position] ??
                                        stand.position}
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
                                        <span className="text-[#5fae5f]">
                                            Target Capacity
                                        </span>
                                        <span className="font-bold text-[#c8ffb0]">
                                            {formatNumber(
                                                stand.construction
                                                    .target_capacity,
                                            )}{' '}
                                            seats
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-[#5fae5f]">
                                            Completes
                                        </span>
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
                                        disableDecrease={
                                            targetCapacity - CAPACITY_STEP <
                                            stand.capacity
                                        }
                                        disableIncrease={
                                            targetCapacity + CAPACITY_STEP >
                                            maximumCapacity
                                        }
                                        onDecrease={() =>
                                            setTargetCapacity((current) =>
                                                Math.max(
                                                    stand.capacity,
                                                    current - CAPACITY_STEP,
                                                ),
                                            )
                                        }
                                        onIncrease={() =>
                                            setTargetCapacity((current) =>
                                                Math.min(
                                                    maximumCapacity,
                                                    current + CAPACITY_STEP,
                                                ),
                                            )
                                        }
                                    />

                                    <div className="flex flex-col gap-2 border-t border-[#1f3a1f] pt-4 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-[#5fae5f]">
                                                Expansion Cost
                                            </span>
                                            <span className="font-bold text-[#c8ffb0]">
                                                {previewLoading
                                                    ? '...'
                                                    : formatMoney(
                                                          expansionPreview?.cost ??
                                                              0,
                                                      )}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-[#5fae5f]">
                                                Cash Available
                                            </span>
                                            <span className="font-bold text-[#c8ffb0]">
                                                {cashAvailable === null
                                                    ? '—'
                                                    : formatMoney(
                                                          cashAvailable,
                                                      )}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-[#5fae5f]">
                                                Construction Time
                                            </span>
                                            <span className="font-bold text-[#c8ffb0]">
                                                {previewLoading
                                                    ? '...'
                                                    : `${expansionPreview?.duration_weeks ?? 0} week${expansionPreview?.duration_weeks === 1 ? '' : 's'}`}
                                            </span>
                                        </div>
                                    </div>

                                    {capacityIncrease > 0 && (
                                        <PaymentMethodFields
                                            paymentMethod={standPaymentMethod}
                                            lengthYears={standLengthYears}
                                            onPaymentMethodChange={
                                                setStandPaymentMethod
                                            }
                                            onLengthYearsChange={
                                                setStandLengthYears
                                            }
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
                                        disabled={
                                            capacityIncrease <= 0 ||
                                            isSubmittingStand ||
                                            previewLoading
                                        }
                                        className="w-full cursor-pointer border border-[#39ff14]/60 bg-[#0f2a0f] py-2 text-sm font-bold tracking-wide text-[#39ff14] uppercase hover:bg-[#153a15] disabled:cursor-not-allowed disabled:opacity-40 [text-shadow:0_0_5px_rgba(57,255,20,0.6)]"
                                    >
                                        {isSubmittingStand
                                            ? 'Expanding...'
                                            : 'Expand Stand'}
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
                            {stadium.commercial_venues.length}/
                            {stadium.commercial_limit}
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
                                    isDemolishing={
                                        demolishingVenueId === venue.id
                                    }
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
                        index={Math.min(
                            buildModalIndex,
                            Math.max(0, (buildableCategories?.length ?? 1) - 1),
                        )}
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
