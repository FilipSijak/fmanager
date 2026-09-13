import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import GameLayout from '@/layouts/GameLayout';
import stadiumRoutes from '@/routes/stadium';

const CANVAS_W = 1536;
const CANVAS_H = 1024;

type Hotspot = {
    label: string;
    /** Shown in the hover tooltip, e.g. "Bar Management". */
    destination: string;
    href: string;
    /** [x0, y0, x1, y1] in stadium-3-2.png's own 1536x1024 pixel space. */
    box: [number, number, number, number];
};

// Boxes were read off stadium-3-2.png by eye (grid overlay at 50px spacing), so
// they're approximate rectangles over each isometric building rather than exact outlines.
// Stadium is listed first and the shops after so overlapping edges resolve to the shop.
const hotspots: Hotspot[] = [
    {
        label: 'Stadium',
        destination: 'Stadium Construction',
        href: stadiumRoutes.construction().url,
        box: [105, 10, 1480, 535],
    },
    {
        label: 'The Tavern (Bar)',
        destination: 'Bar Management',
        href: stadiumRoutes.bars().url,
        box: [90, 495, 345, 705],
    },
    {
        label: 'King Wok Chinese Food',
        destination: 'Restaurant Management',
        href: stadiumRoutes.restaurants().url,
        box: [350, 515, 605, 705],
    },
    {
        label: "Mario's Pizza",
        destination: 'Restaurant Management',
        href: stadiumRoutes.restaurants().url,
        box: [610, 545, 880, 755],
    },
    {
        label: 'Vinyl Records',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [945, 550, 1160, 705],
    },
    {
        label: 'Neon Nights (Bar)',
        destination: 'Bar Management',
        href: stadiumRoutes.bars().url,
        box: [1200, 525, 1520, 710],
    },
    {
        label: 'Sports Gear',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [85, 770, 345, 1020],
    },
    {
        label: 'Green Grocer',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [350, 775, 605, 1020],
    },
    {
        label: 'Good Thai Restaurant',
        destination: 'Restaurant Management',
        href: stadiumRoutes.restaurants().url,
        box: [610, 760, 885, 1020],
    },
    {
        label: 'City Cuts Barbershop',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [950, 775, 1195, 1020],
    },
    {
        label: 'Books',
        destination: 'Shop Management',
        href: stadiumRoutes.shops().url,
        box: [1210, 775, 1480, 1020],
    },
];

export default function Stadium() {
    const [hovered, setHovered] = useState<string | null>(null);
    const hoveredHotspot = hotspots.find((h) => h.label === hovered);

    const containerRef = useRef<HTMLDivElement>(null);
    const [sceneSize, setSceneSize] = useState({
        width: CANVAS_W,
        height: CANVAS_H,
    });

    useEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return;
        }

        const fitToContainer = () => {
            const { clientWidth, clientHeight } = container;
            const scale = Math.min(
                clientWidth / CANVAS_W,
                clientHeight / CANVAS_H,
            );
            setSceneSize({ width: CANVAS_W * scale, height: CANVAS_H * scale });
        };

        fitToContainer();
        const observer = new ResizeObserver(fitToContainer);
        observer.observe(container);
        return () => observer.disconnect();
    }, []);

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Stadium" />
            <main className="flex h-screen flex-col overflow-hidden bg-[#10151c] text-white">
                {/* dark overlay filling the whole content area, fading in from the scene's edges out
                    to the sidebar, rather than a flat background color swap */}
                <section
                    ref={containerRef}
                    className="flex flex-1 items-center justify-center overflow-hidden p-4"
                    style={{
                        background:
                            'radial-gradient(ellipse at center, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.92) 75%)',
                    }}
                >
                    <div
                        className="relative overflow-hidden shadow-2xl"
                        style={{
                            width: sceneSize.width,
                            height: sceneSize.height,
                        }}
                    >
                        <img
                            src="/game-assets/stadium/stadium-3-2.png"
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
