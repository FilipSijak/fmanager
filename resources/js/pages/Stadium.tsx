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

// All stadium images share one native size and one footprint calibration - the leftmost
// and rightmost opaque pixels of the silhouette (the two outer corners/floodlights,
// whichever extend furthest). They're renders from the same camera/export setup, so this
// one measurement applies to every stadium image; swapping which file renders (e.g. based
// on the club's stadium build/upgrade tier) is purely picking a different filename below.
//
// If a future stadium image turns out NOT to share this footprint (e.g. a different
// camera angle), re-measure it with:
//   from PIL import Image
//   im = Image.open(path).convert('RGBA'); px = im.load(); w, h = im.size
//   left = min(((x, y) for y in range(h) for x in range(w) if px[x, y][3] > 10), key=lambda p: p[0])
//   right = max(((x, y) for y in range(h) for x in range(w) if px[x, y][3] > 10), key=lambda p: p[0])
// and give that file its own entry in STADIUM_IMAGES with an overridden left/right.
const STADIUM_NATIVE_WIDTH = 1448;
const STADIUM_NATIVE_HEIGHT = 1086;
const STADIUM_LEFT: Point = [13, 521];
const STADIUM_RIGHT: Point = [1444, 371];

// The stadium's plot on base-no-tiles.png, as the scene-space points STADIUM_LEFT/RIGHT
// must land on. Refined by numerically minimizing pixel error against scene-positions.png
// (a reference mockup of the correct placement) over scale, rotation and position - not
// just the initial manually-measured corners - so this is accurate to within source-image
// rendering noise.
const STADIUM_SLOT_LEFT: Point = [163.1, 292.0];
const STADIUM_SLOT_RIGHT: Point = [1353.9, 144.2];

// Every stadium image the game can show, keyed by its filename in /game-assets/stadium/.
// Swapping which one renders - e.g. based on the club's stadium build/upgrade state - is
// just picking a different key via activeStadiumFile below; no repositioning needed.
const STADIUM_IMAGES: Record<string, string> = {
    'stadium-full.png': 'Stadium',
    'construction-stadium-1.png': 'Stadium (under construction)',
};

// TODO: derive this from game state (e.g. the club's stadium build/upgrade tier) once
// that's wired up.
const activeStadiumFile = 'stadium-full.png';

// Similarity transform (scale + rotation + translation, no shear) mapping the stadium's
// own left/right footprint points onto the fixed scene-space slot. See the constants
// above for what "the slot" and "the footprint" mean.
function fitToSlot(file: string, label: string): Placement {
    const [d1x, d1y] = STADIUM_SLOT_LEFT;
    const [d2x, d2y] = STADIUM_SLOT_RIGHT;
    const [s1x, s1y] = STADIUM_LEFT;
    const [s2x, s2y] = STADIUM_RIGHT;

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
        label,
        z: 1,
        x: d1x - scale * s1x,
        y: d1y - scale * s1y,
        w: STADIUM_NATIVE_WIDTH * scale,
        rotate,
        rotateOrigin: STADIUM_SLOT_LEFT,
    };
}

const NATIVE_WIDTH: Record<string, number> = {
    'base-no-tiles.png': 1448,
    ...Object.fromEntries(
        Object.keys(STADIUM_IMAGES).map((file) => [file, STADIUM_NATIVE_WIDTH]),
    ),
};
const NATIVE_HEIGHT: Record<string, number> = {
    'base-no-tiles.png': 1086,
    ...Object.fromEntries(
        Object.keys(STADIUM_IMAGES).map((file) => [
            file,
            STADIUM_NATIVE_HEIGHT,
        ]),
    ),
};

const CANVAS_W = 1448;
const CANVAS_H = 1086;

const placements: Placement[] = [
    {
        file: 'base-no-tiles.png',
        x: 0,
        y: 0,
        w: CANVAS_W,
        z: 0,
        label: 'Base layout',
        rotate: 0,
        rotateOrigin: [0, 0],
    },
    fitToSlot(activeStadiumFile, STADIUM_IMAGES[activeStadiumFile]),
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
