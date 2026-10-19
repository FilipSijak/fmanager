import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { apply } from '@/actions/App/Http/Controllers/ManagerJobController';
import GameLayout from '@/layouts/GameLayout';

type Vacancy = {
    club_id: number;
    club_name: string;
    league_name: string | null;
    required_reputation: number;
    applied: boolean;
};

export default function Jobs({
    is_unemployed: isUnemployed,
    reputation,
    reputation_tolerance: reputationTolerance,
    vacancies,
}: {
    is_unemployed: boolean;
    reputation: number | null;
    reputation_tolerance: number;
    vacancies: Vacancy[];
}) {
    const { errors } = usePage<{ errors: { application?: string } }>().props;
    const [applyingTo, setApplyingTo] = useState<number | null>(null);

    function applyFor(clubId: number) {
        setApplyingTo(clubId);
        router.post(
            apply.url(clubId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setApplyingTo(null),
            },
        );
    }

    return (
        <GameLayout active="Continue Game">
            <Head title="Jobs" />

            <header className="flex h-[92px] flex-col items-center justify-center border-b border-black bg-black px-6">
                <h1 className="text-3xl font-black tracking-tight text-[#3355dd]">
                    Manager Vacancies
                </h1>
                {reputation !== null && (
                    <p className="text-sm font-semibold text-white">
                        Your reputation: {reputation}
                    </p>
                )}
            </header>

            <main className="flex flex-1 flex-col items-center gap-4 overflow-y-auto bg-[#0c0c14] px-6 py-6 text-white">
                <p className="max-w-2xl text-center text-sm">
                    {isUnemployed
                        ? `Clubs only consider managers whose reputation is within ${reputationTolerance} of what they are looking for. Boards answer applications on the next day.`
                        : 'You can apply for jobs once you are without a club.'}
                </p>
                {errors.application && (
                    <p role="alert" className="text-sm font-semibold text-red-400">
                        {errors.application}
                    </p>
                )}
                {vacancies.length === 0 && (
                    <p className="font-semibold">
                        No clubs are looking for a manager right now.
                    </p>
                )}
                {vacancies.map((vacancy) => {
                    const withinReach =
                        reputation !== null &&
                        Math.abs(reputation - vacancy.required_reputation) <=
                            reputationTolerance;

                    return (
                        <div
                            key={vacancy.club_id}
                            className="flex w-full max-w-2xl items-center justify-between gap-4 rounded border border-[#3355dd] bg-black/30 p-4"
                        >
                            <div>
                                <p className="font-bold">{vacancy.club_name}</p>
                                <p className="text-sm text-white/70">
                                    {vacancy.league_name ?? 'No league'} —
                                    reputation around{' '}
                                    {vacancy.required_reputation}
                                    {isUnemployed &&
                                        !withinReach &&
                                        ' (out of reach)'}
                                </p>
                            </div>
                            <button
                                type="button"
                                disabled={
                                    !isUnemployed ||
                                    vacancy.applied ||
                                    applyingTo !== null
                                }
                                onClick={() => applyFor(vacancy.club_id)}
                                className="rounded border border-[#3355dd] px-4 py-2 font-semibold text-[#f5f000] hover:bg-white/10 disabled:opacity-50"
                            >
                                {vacancy.applied
                                    ? 'Applied'
                                    : applyingTo === vacancy.club_id
                                      ? 'Applying…'
                                      : 'Apply'}
                            </button>
                        </div>
                    );
                })}
            </main>
        </GameLayout>
    );
}
