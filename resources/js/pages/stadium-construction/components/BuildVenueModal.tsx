import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import type { BuildableCategory, PaymentMethod } from '../types';
import {
    availableSizesForCategory,
    formatMoney,
    monoFont,
    SIZE_OPTIONS,
} from '../utils';
import PaymentMethodFields from './PaymentMethodFields';
import ToggleButton from './ToggleButton';

export default function BuildVenueModal({
    categories,
    index,
    selectedSize,
    paymentMethod,
    lengthYears,
    isSubmitting,
    error,
    onNavigate,
    onSelectSize,
    onPaymentMethodChange,
    onLengthYearsChange,
    onBuild,
    onClose,
}: {
    categories: BuildableCategory[] | null;
    index: number;
    selectedSize: 1 | 2 | 3 | null;
    paymentMethod: PaymentMethod;
    lengthYears: number;
    isSubmitting: boolean;
    error: string | null;
    onNavigate: (direction: -1 | 1) => void;
    onSelectSize: (size: 1 | 2 | 3) => void;
    onPaymentMethodChange: (method: PaymentMethod) => void;
    onLengthYearsChange: (years: number) => void;
    onBuild: () => void;
    onClose: () => void;
}) {
    const category = categories?.[index] ?? null;
    const availableSizes = category ? availableSizesForCategory(category) : [];
    const cost =
        category && selectedSize
            ? category.costs[
                  SIZE_OPTIONS.find((o) => o.value === selectedSize)!.key
              ]
            : undefined;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6">
            <div
                className="relative w-full max-w-sm border-2 border-[#39ff14]/50 bg-[#04120a] shadow-[0_0_30px_rgba(57,255,20,0.2)]"
                style={monoFont}
            >
                <div className="flex items-center justify-between border-b border-[#1f3a1f] bg-[#08210f] px-5 py-3">
                    <span className="text-xs font-bold tracking-widest text-[#5fae5f] uppercase">
                        Build New Venue
                    </span>
                    <button
                        type="button"
                        aria-label="Close"
                        onClick={onClose}
                        className="flex size-7 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#c8ffb0] hover:bg-[#0f2a0f]"
                    >
                        <X size={14} />
                    </button>
                </div>

                {categories === null ? (
                    <p className="p-6 text-center text-xs text-[#5fae5f]">
                        Loading available venues...
                    </p>
                ) : category ? (
                    <div className="flex flex-col gap-4 p-6">
                        <div className="flex items-center justify-center gap-4">
                            <button
                                type="button"
                                aria-label="Previous venue"
                                onClick={() => onNavigate(-1)}
                                className="flex size-8 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
                            >
                                <ChevronLeft size={18} />
                            </button>
                            <p className="min-w-[160px] text-center text-lg font-bold tracking-wide text-[#39ff14] uppercase [text-shadow:0_0_6px_rgba(57,255,20,0.6)]">
                                {category.name}
                            </p>
                            <button
                                type="button"
                                aria-label="Next venue"
                                onClick={() => onNavigate(1)}
                                className="flex size-8 cursor-pointer items-center justify-center border border-[#1f3a1f] bg-[#020a05] text-[#39ff14] hover:bg-[#0f2a0f]"
                            >
                                <ChevronRight size={18} />
                            </button>
                        </div>

                        {category.description && (
                            <p className="text-center text-xs text-[#5fae5f]">
                                {category.description}
                            </p>
                        )}

                        <div className="grid grid-cols-3 gap-2">
                            {availableSizes.map((option) => (
                                <ToggleButton
                                    key={option.value}
                                    label={option.label}
                                    active={selectedSize === option.value}
                                    onClick={() => onSelectSize(option.value)}
                                />
                            ))}
                        </div>

                        <div className="flex flex-col gap-1 border-t border-[#1f3a1f] pt-4 text-sm">
                            <div className="flex justify-between">
                                <span className="text-[#5fae5f]">
                                    Build Cost
                                </span>
                                <span className="font-bold text-[#c8ffb0]">
                                    {cost !== undefined
                                        ? formatMoney(cost)
                                        : '—'}
                                </span>
                            </div>
                        </div>

                        <PaymentMethodFields
                            paymentMethod={paymentMethod}
                            lengthYears={lengthYears}
                            onPaymentMethodChange={onPaymentMethodChange}
                            onLengthYearsChange={onLengthYearsChange}
                        />

                        {error && (
                            <p className="text-center text-xs font-bold text-[#ff6b6b]">
                                {error}
                            </p>
                        )}

                        <button
                            type="button"
                            onClick={onBuild}
                            disabled={isSubmitting || selectedSize === null}
                            className="w-full cursor-pointer border border-[#39ff14]/60 bg-[#0f2a0f] py-2 text-sm font-bold tracking-wide text-[#39ff14] uppercase hover:bg-[#153a15] disabled:cursor-not-allowed disabled:opacity-40 [text-shadow:0_0_5px_rgba(57,255,20,0.6)]"
                        >
                            {isSubmitting
                                ? 'Building...'
                                : `Build ${category.name}`}
                        </button>
                    </div>
                ) : (
                    <p className="p-6 text-center text-xs text-[#5fae5f]">
                        All available venues have been built.
                    </p>
                )}
            </div>
        </div>
    );
}
