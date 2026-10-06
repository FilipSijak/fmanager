export type PlayerAttributeCategory =
    | 'technical'
    | 'mental'
    | 'physical'
    | 'goalkeeping';

export type PlayerProfileData = {
    id: number;
    first_name: string;
    last_name: string;
    position: string;
    country_code: string | null;
    dob: string | null;
    is_retired: boolean;
    club: { id: number; name: string } | null;
    attributes: Record<PlayerAttributeCategory, Record<string, number>>;
};
