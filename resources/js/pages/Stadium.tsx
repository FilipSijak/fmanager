import { Head, Link } from '@inertiajs/react';
import GameLayout from '@/layouts/GameLayout';
import stadiumRoutes from '@/routes/stadium';

const CANVAS_W = 1448;
const CANVAS_H = 1086;

type Hotspot = {
    label: string;
    href: string;
    /** [x0, y0, x1, y1] in stadium-complete.png's own 1448x1086 pixel space. */
    box: [number, number, number, number];
};

// Boxes were read off stadium-complete.png by eye (grid overlay at 50px spacing), so
// they're approximate rectangles over each isometric building rather than exact outlines.
// Stadium is listed first and the shops after so overlapping edges resolve to the shop.
const hotspots: Hotspot[] = [
    {
        label: 'Stadium',
        href: stadiumRoutes.construction().url,
        box: [100, 15, 1410, 560],
    },
    {
        label: 'The Tavern (Bar)',
        href: stadiumRoutes.bars().url,
        box: [60, 460, 300, 705],
    },
    {
        label: 'King Wok Chinese Food',
        href: stadiumRoutes.restaurants().url,
        box: [300, 480, 545, 705],
    },
    {
        label: "Mario's Pizza",
        href: stadiumRoutes.restaurants().url,
        box: [545, 555, 790, 725],
    },
    {
        label: 'Vinyl Records',
        href: stadiumRoutes.shops().url,
        box: [895, 565, 1075, 715],
    },
    {
        label: 'Neon Nights (Bar)',
        href: stadiumRoutes.bars().url,
        box: [1160, 540, 1410, 715],
    },
    {
        label: 'Sports Gear',
        href: stadiumRoutes.shops().url,
        box: [85, 770, 330, 975],
    },
    {
        label: 'Green Grocer',
        href: stadiumRoutes.shops().url,
        box: [330, 775, 590, 980],
    },
    {
        label: 'Good Thai Restaurant',
        href: stadiumRoutes.restaurants().url,
        box: [590, 770, 855, 1015],
    },
    {
        label: 'City Cuts Barbershop',
        href: stadiumRoutes.shops().url,
        box: [895, 775, 1140, 980],
    },
    {
        label: 'Books',
        href: stadiumRoutes.shops().url,
        box: [1140, 770, 1400, 975],
    },
];

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
                        {hotspots.map((h) => {
                            const [x0, y0, x1, y1] = h.box;
                            return (
                                <Link
                                    key={h.label}
                                    href={h.href}
                                    title={h.label}
                                    aria-label={h.label}
                                    className="absolute cursor-pointer bg-transparent transition-colors hover:bg-[#f5f000]/20"
                                    style={{
                                        left: `${(x0 / CANVAS_W) * 100}%`,
                                        top: `${(y0 / CANVAS_H) * 100}%`,
                                        width: `${((x1 - x0) / CANVAS_W) * 100}%`,
                                        height: `${((y1 - y0) / CANVAS_H) * 100}%`,
                                    }}
                                />
                            );
                        })}
                    </div>
                </section>
            </main>
        </GameLayout>
    );
}
