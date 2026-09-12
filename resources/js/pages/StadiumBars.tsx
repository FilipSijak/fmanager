import { Head } from '@inertiajs/react';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

export default function StadiumBars() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Bars" />
            <main className="relative flex min-h-screen flex-1 flex-col bg-[#10151c] text-white">
                <StadiumSubPageHeader />
                <section className="flex flex-1 items-center justify-center p-8 text-[#aab4bd]">
                    <p>Bar management will go here.</p>
                </section>
            </main>
        </GameLayout>
    );
}
