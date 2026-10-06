import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';
import BuildVenueModal from './components/BuildVenueModal';
import BuiltVenueRow from './components/BuiltVenueRow';
import PaymentMethodFields from './components/PaymentMethodFields';
import StepperRow from './components/StepperRow';
import { useStadiumConstruction } from './useStadiumConstruction';
import {
    CAPACITY_STEP,
    formatMoney,
    formatNumber,
    monoFont,
    STAND_LABELS,
} from './utils';

export default function StadiumConstruction() {
    const {
        stadium,
        cashAvailable,
        loadError,
        stand,
        goToStand,

        targetCapacity,
        setTargetCapacity,
        expansionPreview,
        previewLoading,
        standPaymentMethod,
        setStandPaymentMethod,
        standLengthYears,
        setStandLengthYears,
        standError,
        isSubmittingStand,
        submitStandExpansion,
        underConstruction,
        capacityIncrease,
        maximumCapacity,

        buildableCategories,
        isBuildModalOpen,
        openBuildModal,
        closeBuildModal,
        buildModalIndex,
        navigateBuildModal,
        selectedSize,
        setSelectedSize,
        venuePaymentMethod,
        setVenuePaymentMethod,
        venueLengthYears,
        setVenueLengthYears,
        venueError,
        isSubmittingVenue,
        submitVenueBuild,

        demolishingVenueId,
        demolishVenue,
    } = useStadiumConstruction();

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
                        onClose={closeBuildModal}
                    />
                )}
            </main>
        </GameLayout>
    );
}
