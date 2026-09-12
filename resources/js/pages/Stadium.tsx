import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import stadiumRoutes from '@/routes/stadium';

const CANVAS_W = 1448;
const CANVAS_H = 1086;

type Hotspot = {
    label: string;
    /** Shown in the hover tooltip, e.g. "Bar Management". */
    destination: string;
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
        destination: 'Stadium Construction',
        href: stadiumRoutes.construction().url,
        box: [100, 15, 1410, 560],
    },
    {
        label: 'The Tavern (Bar)',
        destination: 'Bar Management',
        href: stadiumRoutes.bars().url,
        box: [60, 460, 300, 705],
    },
    {
        label: 'King Wok Chinese Food',
        destination: 'Restaurant Management',
        href: stadiumRoutes.restaurants().url,
        box: [300, 480, 545, 705],
    },
    {
        label: "Mario's Pizza",
        destination: 'Restaurant Management',
        href: stadiumRoutes.restaurants().url,
        box: [545, 555, 790, 725],
    },
    {
        label: 'Vinyl Records',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [895, 565, 1075, 715],
    },
    {
        label: 'Neon Nights (Bar)',
        destination: 'Bar Management',
        href: stadiumRoutes.bars().url,
        box: [1160, 540, 1410, 715],
    },
    {
        label: 'Sports Gear',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [85, 770, 330, 975],
    },
    {
        label: 'Green Grocer',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [330, 775, 590, 980],
    },
    {
        label: 'Good Thai Restaurant',
        destination: 'Restaurant Management',
        href: stadiumRoutes.restaurants().url,
        box: [590, 770, 855, 1015],
    },
    {
        label: 'City Cuts Barbershop',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [895, 775, 1140, 980],
    },
    {
        label: 'Books',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [1140, 770, 1400, 975],
    },
];

export default function Stadium() {
    const [hovered, setHovered] = useState<string | null>(null);
    const hoveredHotspot = hotspots.find((h) => h.label === hovered);

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Stadium" />
            <main className="flex min-h-screen flex-1 flex-col bg-[#10151c] text-white">
                {/* dark overlay filling the whole content area, fading in from the scene's edges out
                    to the sidebar, rather than a flat background color swap */}
                <section
                    className="flex min-h-[560px] flex-1 items-center justify-center overflow-hidden p-4"
                    style={{
                        background:
                            'radial-gradient(ellipse at center, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.92) 75%)',
                    }}
                >
                    <div
                        className="relative w-full max-w-[1200px] overflow-hidden shadow-2xl"
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
                                    aria-label={h.destination}
                                    className="absolute cursor-pointer"
                                    onMouseEnter={() => setHovered(h.label)}
                                    onMouseLeave={() =>
                                        setHovered((current) =>
                                            current === h.label
                                                ? null
                                                : current,
                                        )
                                    }
                                    style={{
                                        left: `${(x0 / CANVAS_W) * 100}%`,
                                        top: `${(y0 / CANVAS_H) * 100}%`,
                                        width: `${((x1 - x0) / CANVAS_W) * 100}%`,
                                        height: `${((y1 - y0) / CANVAS_H) * 100}%`,
                                    }}
                                />
                            );
                        })}
                        {hoveredHotspot && (
                            <div
                                className="pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded border border-[#4a5662] bg-[#202831] px-3 py-1.5 text-xs font-bold whitespace-nowrap text-[#f5f000] shadow-lg"
                                style={{
                                    left: `${((hoveredHotspot.box[0] + hoveredHotspot.box[2]) / 2 / CANVAS_W) * 100}%`,
                                    top: `${(hoveredHotspot.box[1] / CANVAS_H) * 100}%`,
                                    marginTop: '-8px',
                                }}
                            >
                                {hoveredHotspot.destination}
                            </div>
                        )}
                    </div>
                </section>
            </main>
        </GameLayout>
    );
}
