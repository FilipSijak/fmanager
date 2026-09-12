import { Head } from '@inertiajs/react';
import GameLayout from '@/layouts/GameLayout';

const ASSET = '/game-assets/stadium/';

type Placement = {
    file: string;
    label: string;
    z: number;
    x?: number;
    y?: number;
    w?: number;
    /** Degrees clockwise, applied around rotateOrigin (defaults to the placement's own x,y). */
    rotate?: number;
    rotateOrigin?: [number, number];
};

const NATIVE_WIDTH: Record<string, number> = {
    'base-layout.png': 1448,
    'stadium-full.png': 1448,
};
const NATIVE_HEIGHT: Record<string, number> = {
    'base-layout.png': 1086,
    'stadium-full.png': 1086,
};

const CANVAS_W = 1448;
const CANVAS_H = 1086;

// base-layout.png is the base layout for the scene. stadium-full.png's position/size/
// rotation was measured directly from layoutin-instructions.png, a reference image
// showing the correct final composite on this exact layout (verified pixel-identical
// background), by matching the stadium's own rounded-corner extremes (its widest feature,
// wider than the floodlights on this rounded design) between the two images. Size was
// right on the first pass, but the corner-anchored position pushed it onto the road on
// the right and left too much gap to the grass patch on the left - shifted left by 80px
// (pure translation, size/rotation unchanged) to match the reference's margins on both.
const placements: Placement[] = [
    {
        file: 'base-layout.png',
        x: 0,
        y: 0,
        w: CANVAS_W,
        z: 0,
        label: 'Base layout',
    },
    {
        file: 'stadium-full.png',
        x: 188,
        y: -140,
        w: 1150,
        z: 1,
        label: 'Stadium',
        rotate: -0.66,
        rotateOrigin: [1335, 155],
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
                        {/* viewBox scales and centers the fixed-size scene to fit the container at any viewport width */}
                        <svg
                            viewBox={`0 0 ${CANVAS_W} ${CANVAS_H}`}
                            className="absolute inset-0 h-full w-full"
                        >
                            <title>Stadium scene</title>
                            {[...placements]
                                .sort((a, b) => a.z - b.z)
                                .map((p) => {
                                    const ratio =
                                        (p.w ?? NATIVE_WIDTH[p.file]) /
                                        NATIVE_WIDTH[p.file];
                                    const [ox, oy] = p.rotateOrigin ?? [
                                        p.x ?? 0,
                                        p.y ?? 0,
                                    ];
                                    return (
                                        <image
                                            key={p.file}
                                            href={ASSET + p.file}
                                            x={p.x}
                                            y={p.y}
                                            width={p.w}
                                            height={
                                                NATIVE_HEIGHT[p.file] * ratio
                                            }
                                            transform={
                                                p.rotate
                                                    ? `rotate(${p.rotate} ${ox} ${oy})`
                                                    : undefined
                                            }
                                        >
                                            <title>{p.label}</title>
                                        </image>
                                    );
                                })}
                        </svg>
                    </div>
                </section>
            </main>
        </GameLayout>
    );
}
