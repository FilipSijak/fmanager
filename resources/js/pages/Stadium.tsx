import { Head } from '@inertiajs/react';
import GameLayout from '@/layouts/GameLayout';

const ASSET = '/game-assets/stadium/';

type Point = [number, number];

type Placement = {
    file: string;
    label: string;
    z: number;
    x: number;
    y: number;
    w: number;
    /** Degrees clockwise, applied around rotateOrigin. */
    rotate: number;
    rotateOrigin: Point;
};

/**
 * A stadium image's own footprint, in its native pixel space: the leftmost and rightmost
 * opaque pixels of its silhouette (the two outer corners/floodlights, whichever extend
 * furthest). Trivial to measure for any new image with a Python one-liner:
 *
 *   from PIL import Image
 *   im = Image.open(path).convert('RGBA'); px = im.load(); w, h = im.size
 *   left = min(((x, y) for y in range(h) for x in range(w) if px[x, y][3] > 10), key=lambda p: p[0])
 *   right = max(((x, y) for y in range(h) for x in range(w) if px[x, y][3] > 10), key=lambda p: p[0])
 *
 * That's the only calibration a new stadium image needs - fitToSlot() below does the rest.
 */
type StadiumVariant = {
    label: string;
    nativeWidth: number;
    nativeHeight: number;
    left: Point;
    right: Point;
};

// The stadium's plot on base-layout.png, as the scene-space points its own left/right
// footprint corners must land on. Measured once from layoutin-instructions.png (a
// reference mockup of the correct placement) and independent of any particular stadium
// image - adding more entries to stadiumVariants below reuses this unchanged.
const STADIUM_SLOT_LEFT: Point = [199.8, 286.9];
const STADIUM_SLOT_RIGHT: Point = [1334.8, 154.6];

// Registry of every stadium image the game can show, keyed by its filename in
// /game-assets/stadium/. Swapping which one renders - e.g. based on the club's stadium
// build/upgrade state - is then just picking a different key via activeStadiumFile below;
// no repositioning, because fitToSlot() derives the placement from the calibration here.
// A brand-new image (not yet in this registry) needs its left/right points measured once
// - see the comment on StadiumVariant above - before it can be selected this way.
const stadiumVariants: Record<string, StadiumVariant> = {
    'stadium-full.png': {
        label: 'Stadium',
        nativeWidth: 1448,
        nativeHeight: 1086,
        left: [13, 521],
        right: [1444, 371],
    },
};

// TODO: derive this from game state (e.g. the club's stadium build/upgrade tier) once
// that's wired up. Hardcoded for now since only one stadium image exists.
const activeStadiumFile = 'stadium-full.png';

// Similarity transform (scale + rotation + translation, no shear) mapping the variant's
// own left/right footprint points onto the fixed scene-space slot. See STADIUM_SLOT_*
// above for what "the slot" means, and StadiumVariant for what a new image needs to supply.
function fitToSlot(file: string, variant: StadiumVariant): Placement {
    const [d1x, d1y] = STADIUM_SLOT_LEFT;
    const [d2x, d2y] = STADIUM_SLOT_RIGHT;
    const [s1x, s1y] = variant.left;
    const [s2x, s2y] = variant.right;

    const dx = d2x - d1x;
    const dy = d2y - d1y;
    const sx = s2x - s1x;
    const sy = s2y - s1y;
    const denom = sx * sx + sy * sy;

    // a = (d2-d1) / (s2-s1), as complex numbers - gives scale (|a|) and rotation (arg a)
    const aRe = (dx * sx + dy * sy) / denom;
    const aIm = (dy * sx - dx * sy) / denom;
    const scale = Math.hypot(aRe, aIm);
    const rotate = (Math.atan2(aIm, aRe) * 180) / Math.PI;

    return {
        file,
        label: variant.label,
        z: 1,
        x: d1x - scale * s1x,
        y: d1y - scale * s1y,
        w: variant.nativeWidth * scale,
        rotate,
        rotateOrigin: STADIUM_SLOT_LEFT,
    };
}

const NATIVE_WIDTH: Record<string, number> = {
    'base-layout.png': 1448,
    ...Object.fromEntries(
        Object.entries(stadiumVariants).map(([file, v]) => [
            file,
            v.nativeWidth,
        ]),
    ),
};
const NATIVE_HEIGHT: Record<string, number> = {
    'base-layout.png': 1086,
    ...Object.fromEntries(
        Object.entries(stadiumVariants).map(([file, v]) => [
            file,
            v.nativeHeight,
        ]),
    ),
};

const CANVAS_W = 1448;
const CANVAS_H = 1086;

const placements: Placement[] = [
    {
        file: 'base-layout.png',
        x: 0,
        y: 0,
        w: CANVAS_W,
        z: 0,
        label: 'Base layout',
        rotate: 0,
        rotateOrigin: [0, 0],
    },
    fitToSlot(activeStadiumFile, stadiumVariants[activeStadiumFile]),
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
                                    const ratio = p.w / NATIVE_WIDTH[p.file];
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
                                                    ? `rotate(${p.rotate} ${p.rotateOrigin[0]} ${p.rotateOrigin[1]})`
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
