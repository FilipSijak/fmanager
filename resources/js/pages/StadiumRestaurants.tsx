import { Head } from '@inertiajs/react';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

export default function StadiumRestaurants() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Restaurants" />
            <main className="flex min-h-screen flex-1 flex-col bg-[#10151c] text-white">
                <StadiumSubPageHeader title="Restaurants" />
                <section className="flex flex-1 items-center justify-center p-8 text-[#aab4bd]">
                    <p>Restaurant management will go here.</p>
                </section>
            </main>
        </GameLayout>
    );
}
