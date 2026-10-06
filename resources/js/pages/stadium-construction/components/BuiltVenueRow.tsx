import { useState } from 'react';
import type { StadiumCommercialVenueData } from '../types';
import { formatMoney, sizeLabel } from '../utils';

export default function BuiltVenueRow({
    venue,
    onDemolish,
    isDemolishing,
}: {
    venue: StadiumCommercialVenueData;
    onDemolish: () => void;
    isDemolishing: boolean;
}) {
    const [confirming, setConfirming] = useState(false);

    return (
        <div className="flex items-center justify-between gap-4 border-b border-[#132a13] px-2 py-2 text-xs last:border-b-0">
            <div className="min-w-0">
                <p className="truncate font-bold tracking-wide text-[#c8ffb0] uppercase">
                    {venue.category?.name ?? 'Unknown venue'}
                </p>
                <p className="truncate text-[#5fae5f]">
                    {sizeLabel(venue.size)} · Built for{' '}
                    {formatMoney(venue.build_cost)}
                </p>
            </div>
            <button
                type="button"
                disabled={isDemolishing}
                onClick={() => {
                    if (confirming) {
                        onDemolish();
                        setConfirming(false);
                    } else {
                        setConfirming(true);
                    }
                }}
                onBlur={() => setConfirming(false)}
                className="shrink-0 cursor-pointer border border-[#5a1f1f] bg-[#200808] px-2 py-1 text-[10px] font-bold tracking-wide text-[#ff6b6b] uppercase hover:bg-[#3a1010] disabled:cursor-not-allowed disabled:opacity-40"
            >
                {isDemolishing
                    ? 'Demolishing...'
                    : confirming
                      ? 'Confirm?'
                      : 'Demolish'}
            </button>
        </div>
    );
}
