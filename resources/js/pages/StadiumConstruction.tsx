import { Head } from '@inertiajs/react';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

export default function StadiumConstruction() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Stadium Construction" />
            <main className="flex min-h-screen flex-1 flex-col bg-[#10151c] text-white">
                <StadiumSubPageHeader title="Construction" />
                <section className="flex flex-1 items-center justify-center p-8 text-[#aab4bd]">
                    <p>Stadium construction options will go here.</p>
                </section>
            </main>
        </GameLayout>
    );
}
