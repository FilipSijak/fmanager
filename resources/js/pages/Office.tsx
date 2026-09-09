import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import GameLayout from '@/layouts/GameLayout';

const STEP = 50_000;
const MIN_OFFER = 0;
const MAX_OFFER = 5_000_000;

export default function Office() {
    const [offer, setOffer] = useState(1_000_000);
    const [isRigMatchOpen, setIsRigMatchOpen] = useState(false);

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Office" />
            <main className="relative min-h-screen flex-1 overflow-hidden bg-[#303140]">
                <img
                    src="/office-no-window.png"
                    alt="A football manager's office with a desk and filing cabinet"
                    className="pointer-events-none absolute inset-0 size-full object-cover object-center"
                />
                <button
                    type="button"
                    aria-label="Open office interactions"
                    onClick={() => setIsRigMatchOpen(true)}
                    className="absolute inset-0 z-10 cursor-pointer bg-transparent focus:ring-2 focus:ring-white/80 focus:outline-none"
                />
                {isRigMatchOpen && (
                    <div className="absolute top-1/2 left-1/2 z-20 w-[min(90%,440px)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border-[3px] border-[#5c0000] bg-gradient-to-b from-[#3a3a40] to-[#1c1c20] p-1 shadow-[0_15px_35px_rgba(0,0,0,0.55)]">
                        <div className="rounded-xl border border-black/40 bg-[#efe8d8] shadow-[inset_0_2px_6px_rgba(0,0,0,0.15)]">
                            <div className="rounded-t-xl bg-[linear-gradient(180deg,#f04a4a_0%,#c8102e_45%,#7a0a13_100%)] px-4 py-3 text-center">
                                <h2 className="text-xl font-extrabold text-white">
                                    Rig Match
                                </h2>
                            </div>
                            <div className="px-5 py-4">
                                <p className="mb-3 text-center text-sm font-semibold text-slate-800">
                                    How much will you give them to lose the
                                    match?
                                </p>
                                <div className="flex items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        aria-label="Decrease offer"
                                        onClick={() =>
                                            setOffer((value) =>
                                                Math.max(
                                                    MIN_OFFER,
                                                    value - STEP,
                                                ),
                                            )
                                        }
                                        className="flex size-8 items-center justify-center rounded-full bg-orange-500 text-white"
                                    >
                                        <ChevronLeft size={18} />
                                    </button>
                                    <span className="min-w-36 rounded-full border-2 border-yellow-700 bg-yellow-300 px-4 py-1.5 text-center text-lg font-bold text-slate-900">
                                        {offer.toLocaleString()}
                                    </span>
                                    <button
                                        type="button"
                                        aria-label="Increase offer"
                                        onClick={() =>
                                            setOffer((value) =>
                                                Math.min(
                                                    MAX_OFFER,
                                                    value + STEP,
                                                ),
                                            )
                                        }
                                        className="flex size-8 items-center justify-center rounded-full bg-orange-500 text-white"
                                    >
                                        <ChevronRight size={18} />
                                    </button>
                                </div>
                            </div>
                            <div className="flex gap-3 border-t border-[#d8c9a8] px-5 py-3">
                                <button
                                    type="button"
                                    onClick={() => setIsRigMatchOpen(false)}
                                    className="flex-1 rounded-full bg-blue-700 py-2 text-sm font-bold text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIsRigMatchOpen(false)}
                                    className="flex-1 rounded-full bg-blue-700 py-2 text-sm font-bold text-white"
                                >
                                    Offer
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </main>
        </GameLayout>
    );
}
