import { Head } from '@inertiajs/react';
import GameLayout from '@/layouts/GameLayout';

const CANVAS_W = 1448;
const CANVAS_H = 1086;

export default function Stadium() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Stadium" />
            <main className="flex min-h-screen flex-1 flex-col bg-[#10151c] text-white">
                <header className="border-b border-black bg-[#202831] px-5 py-3">
                    <p className="text-xs font-bold tracking-[0.25em] text-[#f5f000] uppercase">
                        Fixed isometric scene
                    </p>
                    <h1 className="text-2xl font-black">Stadium</h1>
                </header>
                <section className="flex min-h-[560px] flex-1 items-center justify-center overflow-hidden bg-[#111820] p-4">
                    <div
                        className="relative w-full max-w-[1200px] overflow-hidden border border-[#5c6670] bg-[#4c4c48] shadow-2xl"
                        style={{ aspectRatio: `${CANVAS_W} / ${CANVAS_H}` }}
                    >
                        <img
                            src="/game-assets/stadium/stadium-complete.png"
                            alt="Stadium and surrounding shops"
                            className="absolute inset-0 h-full w-full"
                        />
                    </div>
                </section>
            </main>
        </GameLayout>
    );
}
