export type CompetitionInfo = {
    id: number;
    name: string;
    type: string;
    country_code: string;
    competition_scope: string;
    groups: number | null;
};

export type Standing = {
    position: number;
    club_id: number;
    club_name: string;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    goals_for: number;
    goals_against: number;
    goal_difference: number;
    points: number;
    outcomes: {
        type: 'promotion' | 'relegation' | 'continental' | 'knockout';
        target_name: string;
        entry_stage: string | null;
    }[];
};

export type CompetitionMatch = {
    id: number;
    season_id: number;
    competition_id: number;
    competition_name: string;
    match_start: string | null;
    status: string;
    hometeam_id: number;
    awayteam_id: number;
    home_team: string;
    away_team: string;
    home_team_goals: number | null;
    away_team_goals: number | null;
    knockout_tie_id: number | null;
    leg_number: number | null;
    group_id: number | null;
};

export type KnockoutTie = {
    id: number;
    home_club_id: number | null;
    away_club_id: number | null;
    home_team: string | null;
    away_team: string | null;
    winner_club_id: number | null;
    status: string;
};

export type KnockoutRound = {
    id: number;
    round_number: number;
    bracket_side: string;
    name: string;
    number_of_legs: number;
    status: string;
    ties: KnockoutTie[];
};

export type CompetitionPageProps = {
    competitions: CompetitionInfo[];
    competition: CompetitionInfo | null;
    season: { id: number; start_date: string; end_date: string } | null;
    seasons: { id: number; start_date: string; end_date: string }[];
    currentSeasonId: number;
    clubId: number | null;
    table: Standing[];
    groups: { id: number; table: Standing[] }[];
    matches: CompetitionMatch[];
    rounds: KnockoutRound[];
};
