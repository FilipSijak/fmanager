import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';

const STEP = 50_000;
const MIN_OFFER = 0;
const MAX_OFFER = 5_000_000;

const corkboardLines = ['line-1', 'line-2', 'line-3', 'line-4', 'line-5'];
const cabinetDrawers = ['drawer-1', 'drawer-2', 'drawer-3', 'drawer-4'];
const newspaperLines = [
    'line-1',
    'line-2',
    'line-3',
    'line-4',
    'line-5',
    'line-6',
];
const fernLeaves = [
    { rotate: -55, tint: '#1f5c34' },
    { rotate: -28, tint: '#2b7040' },
    { rotate: -5, tint: '#245f38' },
    { rotate: 18, tint: '#2b7040' },
    { rotate: 42, tint: '#1f5c34' },
    { rotate: 65, tint: '#245f38' },
];
const standWindows = Array.from({ length: 10 }, (_, i) => i);

export default function Office() {
    const [offer, setOffer] = useState(1_000_000);

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Office" />

            <header className="flex h-[92px] items-center justify-center border-b border-black bg-gradient-to-b from-[#7a4a26] to-[#4e2f18] px-6">
                <h1 className="text-3xl font-bold text-[#f0dfc4]">
                    Manager&apos;s Office
                </h1>
            </header>

            <div
                className="relative min-h-[560px] flex-1 overflow-hidden bg-[#c9c9cc]"
                style={{ perspective: '1400px' }}
            >
                {/* back wall, lit from upper-left */}
                <div className="absolute inset-x-0 top-0 h-[58%] bg-[radial-gradient(ellipse_60%_80%_at_22%_8%,#f5f4f8_0%,#dcdbe3_38%,#b6b5bf_78%,#96959f_100%)]" />

                {/* left + right wall slivers, angled toward the floor's vanishing edge for depth */}
                <div
                    className="absolute top-0 left-0 h-[58%] w-[15%] bg-gradient-to-r from-[#8f8e98] to-[#c1c0ca]"
                    style={{
                        clipPath: 'polygon(0 0, 100% 0, 30% 100%, 0 100%)',
                    }}
                />
                <div
                    className="absolute top-0 right-0 h-[58%] w-[15%] bg-gradient-to-l from-[#8f8e98] to-[#c1c0ca]"
                    style={{
                        clipPath: 'polygon(0 0, 100% 0, 100% 100%, 70% 100%)',
                    }}
                />

                {/* floor: real perspective tilt (not just a clipped trapezoid) plus receding carpet lines */}
                <div
                    className="absolute inset-x-0 bottom-0 h-[46%] overflow-hidden bg-[#1c2036]"
                    style={{
                        transform: 'rotateX(55deg)',
                        transformOrigin: 'bottom',
                        boxShadow: 'inset 0 40px 60px -20px rgba(0,0,0,0.7)',
                    }}
                >
                    <div
                        className="absolute inset-0"
                        style={{
                            backgroundImage:
                                'repeating-linear-gradient(90deg, rgba(255,255,255,0.05) 0 2px, transparent 2px 34px)',
                        }}
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-transparent via-transparent to-black/50" />
                </div>
                {/* contact shadow grounding the back wall into the floor */}
                <div className="absolute inset-x-0 top-[52%] h-8 bg-gradient-to-b from-black/35 to-transparent" />

                {/* window: arched top corner, view over the pitch */}
                <div className="absolute top-[5%] right-[3%] h-[48%] w-[36%]">
                    <div
                        className="absolute inset-0 overflow-hidden border-[7px] border-[#94949a] bg-gradient-to-b from-sky-200 via-sky-300 to-emerald-500 shadow-[inset_0_0_0_3px_rgba(0,0,0,0.25),inset_6px_6px_14px_rgba(0,0,0,0.35)]"
                        style={{
                            clipPath:
                                'polygon(14% 0, 78% 0, 100% 10%, 100% 100%, 0 100%, 0 8%)',
                            borderRadius: '0 40% 0 0',
                        }}
                    >
                        {/* mullion */}
                        <div className="absolute inset-y-0 left-1/2 w-[3px] -translate-x-1/2 bg-[#7d7d84]" />

                        {/* pitch */}
                        <div className="absolute inset-x-0 bottom-0 h-[46%] bg-gradient-to-b from-emerald-600 to-emerald-800">
                            <div
                                className="absolute inset-0 opacity-40"
                                style={{
                                    backgroundImage:
                                        'repeating-linear-gradient(90deg, rgba(255,255,255,0.25) 0 8%, transparent 8% 16%)',
                                }}
                            />
                        </div>
                        {/* running track */}
                        <div className="absolute inset-x-0 bottom-[40%] h-[10%] bg-sky-700/70" />
                        {/* stand silhouette */}
                        <div className="absolute inset-x-0 top-[30%] h-[24%] bg-slate-500/80">
                            <div className="flex h-full items-center justify-evenly px-1">
                                {standWindows.map((i) => (
                                    <span
                                        key={i}
                                        className="h-2/3 w-[6%] bg-slate-300/70"
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                    {/* sill */}
                    <div
                        className="absolute -bottom-2 left-[2%] h-2 w-[96%] bg-gradient-to-b from-[#d9d9dc] to-[#8f8f96]"
                        style={{
                            clipPath: 'polygon(0 0, 100% 0, 96% 100%, 4% 100%)',
                        }}
                    />
                </div>

                {/* corkboard */}
                <div className="absolute top-[9%] left-[9%] h-[27%] w-[19%] rounded-sm border-4 border-[#8a5a2f] bg-gradient-to-br from-[#d3a765] to-[#b3813f] p-2 shadow-lg">
                    <div className="relative h-2/3 w-full rotate-[-3deg] bg-[#f7f4ea] p-1.5 shadow-md">
                        <div className="absolute -top-1 left-1/2 size-2 -translate-x-1/2 rounded-full bg-red-700 shadow" />
                        <div className="space-y-1.5">
                            {corkboardLines.map((line) => (
                                <div
                                    key={line}
                                    className="h-[2px] w-full bg-slate-400"
                                />
                            ))}
                        </div>
                    </div>
                </div>

                {/* framed team photo */}
                <div className="absolute top-[12%] right-[40%] h-[17%] w-[10%] overflow-hidden rounded-sm border-4 border-[#5a3a20] bg-[#e9e6df] shadow-lg">
                    <div className="flex h-full flex-col justify-evenly px-1.5 py-1.5">
                        {[0, 1].map((row) => (
                            <div key={row} className="flex justify-evenly">
                                {[0, 1, 2, 3].map((col) => (
                                    <span
                                        key={col}
                                        className="size-1.5 rounded-full bg-[#8a1420]"
                                    />
                                ))}
                            </div>
                        ))}
                    </div>
                </div>

                {/* plant: fern fronds + pot */}
                <div className="absolute top-[42%] right-[35%] h-[20%] w-[10%]">
                    <div className="absolute inset-x-0 top-0 flex h-3/4 items-end justify-center">
                        {fernLeaves.map((leaf) => (
                            <div
                                key={leaf.rotate}
                                className="absolute bottom-1 h-[85%] w-[18%] rounded-full"
                                style={{
                                    backgroundColor: leaf.tint,
                                    transform: `rotate(${leaf.rotate}deg)`,
                                    transformOrigin: 'bottom center',
                                    boxShadow:
                                        'inset -3px 0 4px rgba(0,0,0,0.25)',
                                }}
                            />
                        ))}
                    </div>
                    <div className="relative mx-auto h-1/4 w-3/4 rounded-b-sm bg-gradient-to-b from-[#8a552e] to-[#5c3618] shadow-md">
                        <div className="absolute inset-x-0 -top-1 h-1.5 rounded-full bg-[#a5652f]" />
                    </div>
                </div>

                {/* filing cabinet: top + front + side faces, brushed-metal shading */}
                <div className="absolute bottom-[8%] left-[5%] h-[40%] w-[13%]">
                    <div
                        className="absolute -top-2 left-0 h-3 w-full bg-gradient-to-b from-slate-100 to-slate-400"
                        style={{
                            clipPath: 'polygon(6% 0, 100% 0, 88% 100%, 0 100%)',
                        }}
                    />
                    <div className="absolute inset-0 rounded-sm bg-[linear-gradient(180deg,#cfd3d8_0%,#aeb3ba_8%,#9a9fa7_50%,#7f848c_100%)] shadow-xl">
                        {cabinetDrawers.map((drawer) => (
                            <div
                                key={drawer}
                                className="mx-1.5 mt-[3%] flex h-[22%] items-center rounded-sm bg-gradient-to-b from-slate-200 to-slate-350 shadow-[inset_0_1px_0_rgba(255,255,255,0.5),inset_0_-2px_0_rgba(0,0,0,0.3)]"
                            >
                                <span className="ml-2 h-1.5 w-6 rounded-sm bg-slate-700 shadow-[inset_0_1px_1px_rgba(255,255,255,0.6),inset_0_-1px_1px_rgba(0,0,0,0.5)]" />
                            </div>
                        ))}
                    </div>
                    <div
                        className="absolute top-0 -right-2 h-full w-2 bg-gradient-to-b from-slate-500 to-slate-700"
                        style={{
                            clipPath: 'polygon(0 8%, 100% 0, 100% 100%, 0 92%)',
                        }}
                    />
                </div>

                {/* office chair: high-back leather chair + chrome star base, seen from behind/side */}
                <div className="absolute right-[6%] bottom-0 h-[46%] w-[16%]">
                    <div className="absolute inset-x-[10%] bottom-[2%] -z-10 h-3 rounded-full bg-black/40 blur-sm" />
                    {/* casters + star base */}
                    <div className="absolute inset-x-[10%] bottom-0 h-[4%] rounded-full bg-gradient-to-r from-slate-500 via-slate-200 to-slate-500" />
                    <div className="absolute inset-x-[47%] bottom-[3%] h-[16%] w-[6%] bg-gradient-to-b from-slate-300 to-slate-600" />
                    {/* seat, mostly hidden behind the desk */}
                    <div className="absolute inset-x-[8%] bottom-[16%] h-[16%] rounded-sm bg-[linear-gradient(180deg,#2b2b2f_0%,#151517_100%)] shadow-lg" />
                    {/* high backrest */}
                    <div className="absolute inset-x-0 bottom-[26%] h-[74%] rounded-t-[40%] bg-[linear-gradient(100deg,#45454b_0%,#1c1c1f_50%,#050506_100%)] shadow-2xl">
                        <div className="absolute inset-3 rounded-t-[35%] bg-gradient-to-br from-white/15 via-transparent to-transparent" />
                        <div className="absolute inset-x-0 top-1/2 h-px bg-black/40" />
                    </div>
                </div>

                {/* desk: top surface + front panel + end face, with wood-grain texture */}
                <div className="absolute bottom-[3%] left-[15%] h-[34%] w-[70%]">
                    <div
                        className="absolute inset-x-0 top-0 h-[40%] bg-[linear-gradient(160deg,#e0a35e_0%,#c47f3d_40%,#93591f_100%)] shadow-[0_6px_10px_rgba(0,0,0,0.35)]"
                        style={{
                            clipPath: 'polygon(6% 0, 94% 0, 100% 100%, 0 100%)',
                            backgroundImage:
                                'linear-gradient(160deg,#e0a35e 0%,#c47f3d 40%,#93591f 100%), repeating-linear-gradient(100deg, rgba(0,0,0,0.06) 0 3px, transparent 3px 14px)',
                        }}
                    >
                        {/* desk phone with looped cord */}
                        <div className="absolute top-[36%] left-[3%] h-[42%] w-[11%]">
                            <div className="absolute inset-0 rounded-sm bg-gradient-to-b from-slate-50 to-slate-300 shadow" />
                            <div className="absolute top-[15%] left-[15%] h-[25%] w-[70%] rounded-sm bg-slate-400/70" />
                            <div
                                className="absolute top-[70%] right-[-40%] h-3 w-3 rounded-full border-2 border-slate-400"
                                style={{
                                    borderRightColor: 'transparent',
                                    borderBottomColor: 'transparent',
                                }}
                            />
                        </div>

                        {/* open newspaper */}
                        <div className="absolute top-[32%] left-[18%] h-[64%] w-[26%] -rotate-2 rounded-sm bg-gradient-to-r from-slate-100 to-white p-1.5 shadow">
                            <div className="grid h-full grid-cols-2 gap-1">
                                <div className="space-y-1">
                                    <div className="h-3 w-full rounded-[1px] bg-slate-400" />
                                    {newspaperLines.map((line) => (
                                        <div
                                            key={line}
                                            className="h-[2px] w-full bg-slate-400/80"
                                        />
                                    ))}
                                </div>
                                <div className="space-y-1">
                                    <div className="h-6 w-full bg-slate-300" />
                                    {newspaperLines.slice(0, 4).map((line) => (
                                        <div
                                            key={line}
                                            className="h-[2px] w-full bg-slate-400/80"
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* red folder */}
                        <div className="absolute top-[27%] left-[46%] h-[54%] w-[17%] rounded-sm bg-[linear-gradient(135deg,#e0333f_0%,#a3101c_60%,#7a0a13_100%)] shadow-md" />

                        {/* laptop: base + keyboard + angled screen */}
                        <div className="absolute top-[4%] right-[5%] h-[74%] w-[19%]">
                            <div
                                className="absolute right-0 bottom-0 left-0 h-[24%] rounded-sm bg-gradient-to-b from-slate-400 to-slate-600 shadow"
                                style={{
                                    backgroundImage:
                                        'linear-gradient(180deg,#9aa0a8 0%,#5c636b 100%), repeating-linear-gradient(90deg, rgba(0,0,0,0.15) 0 2px, transparent 2px 6px)',
                                }}
                            />
                            <div
                                className="absolute right-0 bottom-[20%] left-0 h-[80%] origin-bottom rounded-t-sm bg-gradient-to-b from-slate-700 to-slate-900 p-1"
                                style={{ transform: 'scaleY(0.94)' }}
                            >
                                <div className="h-full w-full rounded-sm bg-[radial-gradient(circle_at_30%_20%,#a5f3ec,transparent_60%),linear-gradient(135deg,#67e8f9,#0891b2)]" />
                            </div>
                        </div>
                    </div>

                    <div
                        className="absolute inset-x-0 bottom-0 h-[60%] bg-[linear-gradient(180deg,#8a552e_0%,#5c3618_60%,#3a2210_100%)]"
                        style={{
                            clipPath: 'polygon(2% 0, 98% 0, 100% 100%, 0 100%)',
                            boxShadow: 'inset 0 6px 10px rgba(0,0,0,0.4)',
                        }}
                    >
                        <div className="absolute inset-x-0 top-[18%] h-px bg-black/25" />
                        <div className="absolute top-0 left-1/3 h-full w-px bg-black/20" />
                        <div className="absolute top-0 left-2/3 h-full w-px bg-black/20" />
                    </div>

                    <div
                        className="absolute top-[6%] -right-2 h-[94%] w-3 bg-gradient-to-b from-[#5c3618] to-[#3a2210]"
                        style={{
                            clipPath:
                                'polygon(0 0, 100% 10%, 100% 100%, 0 100%)',
                        }}
                    />

                    {/* remote control, lying on the desk top near the front edge */}
                    <div className="absolute top-[36%] left-[13%] h-[8%] w-[5%] -rotate-6 rounded-sm bg-gradient-to-b from-slate-700 to-slate-900 shadow">
                        <div className="mx-auto mt-0.5 h-[30%] w-1/2 rounded-[1px] bg-slate-500" />
                    </div>
                </div>

                {/* ambient occlusion vignette over the whole scene */}
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_75%_75%_at_50%_40%,transparent_55%,rgba(0,0,0,0.35)_100%)]" />

                {/* Rig Match modal, glossy metallic-red arcade styling */}
                <div className="absolute top-1/2 left-1/2 w-[min(90%,440px)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border-[3px] border-[#5c0000] bg-gradient-to-b from-[#3a3a40] to-[#1c1c20] p-1 shadow-[0_15px_35px_rgba(0,0,0,0.55)]">
                    <div className="rounded-xl border border-black/40 bg-[#efe8d8] shadow-[inset_0_2px_6px_rgba(0,0,0,0.15)]">
                        <div className="rounded-t-xl bg-[linear-gradient(180deg,#f04a4a_0%,#c8102e_45%,#7a0a13_100%)] px-4 py-3 text-center shadow-[inset_0_2px_3px_rgba(255,255,255,0.4),inset_0_-3px_6px_rgba(0,0,0,0.35)]">
                            <h2 className="text-xl font-extrabold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]">
                                Rig Match
                            </h2>
                        </div>

                        <div className="px-5 py-4">
                            <p className="mb-3 text-center text-sm font-semibold text-slate-800">
                                How much will you give them to lose the match?
                            </p>

                            <div className="flex items-center justify-center gap-2">
                                <button
                                    type="button"
                                    aria-label="Decrease offer"
                                    onClick={() =>
                                        setOffer((value) =>
                                            Math.max(MIN_OFFER, value - STEP),
                                        )
                                    }
                                    className="flex size-8 items-center justify-center rounded-full bg-[linear-gradient(180deg,#ffb347_0%,#e07a00_100%)] text-white shadow-[inset_0_1px_1px_rgba(255,255,255,0.6),0_2px_3px_rgba(0,0,0,0.3)] hover:brightness-105"
                                >
                                    <ChevronLeft size={18} />
                                </button>
                                <span className="min-w-36 rounded-full border-2 border-[#a08c1a] bg-[linear-gradient(180deg,#fff85e_0%,#f5df00_100%)] px-4 py-1.5 text-center text-lg font-bold text-slate-900 shadow-[inset_0_2px_4px_rgba(255,255,255,0.6),inset_0_-2px_4px_rgba(0,0,0,0.2)]">
                                    {offer.toLocaleString()}
                                </span>
                                <button
                                    type="button"
                                    aria-label="Increase offer"
                                    onClick={() =>
                                        setOffer((value) =>
                                            Math.min(MAX_OFFER, value + STEP),
                                        )
                                    }
                                    className="flex size-8 items-center justify-center rounded-full bg-[linear-gradient(180deg,#ffb347_0%,#e07a00_100%)] text-white shadow-[inset_0_1px_1px_rgba(255,255,255,0.6),0_2px_3px_rgba(0,0,0,0.3)] hover:brightness-105"
                                >
                                    <ChevronRight size={18} />
                                </button>
                            </div>
                        </div>

                        <div className="flex gap-3 border-t border-[#d8c9a8] px-5 py-3">
                            <button
                                type="button"
                                className="flex-1 rounded-full bg-[linear-gradient(180deg,#3d6ce0_0%,#0031a5_60%,#001d6b_100%)] py-2 text-sm font-bold text-white shadow-[inset_0_1px_1px_rgba(255,255,255,0.5),0_2px_4px_rgba(0,0,0,0.3)] hover:brightness-110"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="flex-1 rounded-full bg-[linear-gradient(180deg,#3d6ce0_0%,#0031a5_60%,#001d6b_100%)] py-2 text-sm font-bold text-white shadow-[inset_0_1px_1px_rgba(255,255,255,0.5),0_2px_4px_rgba(0,0,0,0.3)] hover:brightness-110"
                            >
                                Offer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </GameLayout>
    );
}
