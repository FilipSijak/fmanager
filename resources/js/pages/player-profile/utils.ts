const ATTRIBUTE_LABEL_OVERRIDES: Record<string, string> = {
    freeKick: 'Free Kick',
    first_touch: 'First Touch',
    long_shots: 'Long Shots',
    long_throws: 'Long Throws',
    penalty_taking: 'Penalty Taking',
    of_the_ball: 'Off The Ball',
    workrate: 'Work Rate',
    natural_fitness: 'Natural Fitness',
    one_on_ones: 'One on Ones',
    aerial_reach: 'Aerial Reach',
};

export function attributeLabel(key: string): string {
    if (ATTRIBUTE_LABEL_OVERRIDES[key]) {
        return ATTRIBUTE_LABEL_OVERRIDES[key];
    }

    return key
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export const POSITION_LABELS: Record<string, string> = {
    GK: 'Goalkeeper',
    CB: 'Center Back',
    LB: 'Left Back',
    RB: 'Right Back',
    LWB: 'Left Wingback',
    RWB: 'Right Wingback',
    DMC: 'Defensive Midfielder',
    CM: 'Center Midfielder',
    AMC: 'Attacking Midfielder',
    LW: 'Left Winger',
    RW: 'Right Winger',
    LF: 'Left Forward',
    RF: 'Right Forward',
    CF: 'Center Forward',
    ST: 'Striker',
};

/** Age in full years as of asOfDate (the in-game date, not the real-world date). */
export function ageFromDob(
    dob: string | null,
    asOfDate: string | null,
): number | null {
    if (!dob || !asOfDate) {
        return null;
    }

    const birth = new Date(dob);
    const asOf = new Date(asOfDate);
    let age = asOf.getFullYear() - birth.getFullYear();
    const hasHadBirthdayThisYear =
        asOf.getMonth() > birth.getMonth() ||
        (asOf.getMonth() === birth.getMonth() &&
            asOf.getDate() >= birth.getDate());

    if (!hasHadBirthdayThisYear) {
        age -= 1;
    }

    return age;
}

export function formatDob(dob: string | null): string {
    if (!dob) {
        return 'Unknown';
    }

    const [year, month, day] = dob.split('-');
    return `${day}.${month}.${year.slice(2)}`;
}
