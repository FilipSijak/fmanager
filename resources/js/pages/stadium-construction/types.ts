export type PaymentMethod = 'cash' | 'mortgage';

export type StadiumStandConstructionData = {
    id: number;
    target_capacity: number;
    capacity_increase: number;
    started_at: string;
    completes_at: string;
} | null;

export type StadiumStandData = {
    id: number;
    position: string;
    capacity: number;
    status: string;
    construction: StadiumStandConstructionData;
};

export type StadiumCommercialVenueData = {
    id: number;
    size: 1 | 2 | 3;
    build_cost: number;
    category: {
        id: number;
        slug: string;
        name: string;
        description: string | null;
    } | null;
};

export type StadiumData = {
    id: number;
    name: string;
    type: string;
    commercial_limit: number;
    capacity: number;
    active_capacity: number;
    stands: StadiumStandData[];
    commercial_venues: StadiumCommercialVenueData[];
};

export type BuildableCategory = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    costs: Partial<Record<'small' | 'medium' | 'large', number>>;
};

export type ExpansionPreview = {
    capacity_increase: number;
    maximum_capacity: number;
    cost: number;
    duration_weeks: number;
};
