import { Minus, Plus } from 'lucide-react';

export default function StepperRow({
    label,
    value,
    onDecrease,
    onIncrease,
    disableDecrease,
    disableIncrease,
}: {
    label: string;
    value: string;
    onDecrease: () => void;
    onIncrease: () => void;
    disableDecrease?: boolean;
    disableIncrease?: boolean;
}) {
    return (
        <div className="flex items-center gap-3">
            <button
                type="button"
                aria-label={`Decrease ${label}`}
                onClick={onDecrease}
                disabled={disableDecrease}
                className="flex size-7 shrink-0 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f] disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Minus size={14} />
            </button>
            <span className="min-w-[120px] flex-1 text-center text-sm font-bold text-[#c8ffb0]">
                {value}
            </span>
            <button
                type="button"
                aria-label={`Increase ${label}`}
                onClick={onIncrease}
                disabled={disableIncrease}
                className="flex size-7 shrink-0 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f] disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Plus size={14} />
            </button>
        </div>
    );
}
