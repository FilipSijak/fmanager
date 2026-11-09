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
    is_on_loan: boolean;
    club: { id: number; name: string } | null;
    fitness: {
        condition: number | null;
        morale: number | null;
        is_injured: boolean;
        injuries: { name: string; starts_at: string; ends_at: string }[];
    };
    performance: {
        season_id: number | null;
        current_season_id: number | null;
        seasons: { id: number; start_date: string; end_date: string }[];
        recent: {
            game_id: number;
            date: string;
            opponent: string;
            venue: 'Home' | 'Away';
            home_goals: number | null;
            away_goals: number | null;
            minutes: number;
            goals: number;
            assists: number;
            rating: number;
        }[];
        competitions: {
            id: number;
            name: string;
            appearances: number;
            minutes: number;
            goals: number;
            assists: number;
            average_rating: number;
        }[];
    };
    attributes: Record<PlayerAttributeCategory, Record<string, number>>;
};
