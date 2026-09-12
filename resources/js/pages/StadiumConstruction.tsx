import { Head } from '@inertiajs/react';
import GameLayout from '@/layouts/GameLayout';

export default function StadiumConstruction() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Stadium Construction" />
            <main className="flex min-h-screen flex-1 flex-col bg-[#10151c] text-white">
                <header className="border-b border-black bg-[#202831] px-5 py-3">
                    <p className="text-xs font-bold tracking-[0.25em] text-[#f5f000] uppercase">
                        Stadium
                    </p>
                    <h1 className="text-2xl font-black">Construction</h1>
                </header>
                <section className="flex flex-1 items-center justify-center p-8 text-[#aab4bd]">
                    <p>Stadium construction options will go here.</p>
                </section>
            </main>
        </GameLayout>
    );
}
