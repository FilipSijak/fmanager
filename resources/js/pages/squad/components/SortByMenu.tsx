import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { SortOption } from '../types';
import { SORT_OPTIONS } from '../utils';

export default function SortByMenu({
    value,
    onChange,
}: {
    value: SortOption;
    onChange: (option: SortOption) => void;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        function handleClickOutside(event: MouseEvent) {
            if (
                menuRef.current &&
                !menuRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [isOpen]);

    return (
        <div className="relative" ref={menuRef}>
            <button
                type="button"
                onClick={() => setIsOpen((open) => !open)}
                className="flex items-center gap-2 rounded border border-slate-400 bg-[#c9c9cc] px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-300"
            >
                Sort By
                <ChevronDown size={14} />
            </button>
            {isOpen && (
                <div className="absolute top-full left-0 z-10 mt-1 w-48 overflow-hidden rounded border border-slate-400 bg-[#c9c9cc] shadow-lg">
                    {SORT_OPTIONS.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => {
                                onChange(option.value);
                                setIsOpen(false);
                            }}
                            className={`block w-full px-4 py-2 text-left text-sm font-semibold hover:bg-slate-300 ${
                                option.value === value
                                    ? 'bg-[#0031a5] text-white hover:bg-[#00268a]'
                                    : 'text-slate-800'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
