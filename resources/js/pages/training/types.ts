export type CategoryKey = 'physical' | 'tactical' | 'technical' | 'goalkeeping';
export type TrainingSchedule = Record<CategoryKey, number>;
export type Category = {
    id: number;
    key: CategoryKey;
    name: string;
    threshold: number;
};
export type Intensity = { id: number; name: string };
export type Preset = {
    id: string;
    name: string;
    goalkeeper_only: boolean;
    schedule: TrainingSchedule;
};
export type TrainingAttribute = {
    key: string;
    name: string;
    value: number;
    progress: number;
};
export type TrainingPlayer = {
    id: number;
    name: string;
    position: string;
    condition: number;
    is_injured: boolean;
    resting: boolean;
    last_processed_at: string | null;
    workload: 'Rest' | 'Unavailable' | 'Heavy' | 'Balanced';
    condition_change: number;
    schedule: TrainingSchedule;
    attributes: Record<CategoryKey, TrainingAttribute[]>;
};
export type TrainingProps = {
    club: { id: number; name: string };
    date: string;
    categories: Category[];
    intensities: Intensity[];
    presets: Preset[];
    players: TrainingPlayer[];
};
