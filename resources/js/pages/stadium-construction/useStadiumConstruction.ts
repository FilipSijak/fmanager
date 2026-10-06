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
import type {
    BuildableCategory,
    ExpansionPreview,
    PaymentMethod,
    StadiumData,
} from './types';
import {
    availableSizesForCategory,
    DEFAULT_MORTGAGE_YEARS,
    extractErrorMessage,
} from './utils';

export function useStadiumConstruction() {
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

    function closeBuildModal() {
        setIsBuildModalOpen(false);
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

    const underConstruction = stand?.status === 'under_construction';
    const capacityIncrease = stand ? targetCapacity - stand.capacity : 0;
    const maximumCapacity =
        expansionPreview?.maximum_capacity ?? targetCapacity;

    return {
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
    };
}
