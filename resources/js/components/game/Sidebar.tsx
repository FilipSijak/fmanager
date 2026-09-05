import { ChevronLeft, ChevronRight } from 'lucide-react';

type NavItem = {
    label: string;
    accent?: 'cyan' | 'yellow';
};

const navItems: NavItem[] = [
    { label: 'Continue Game' },
    { label: 'Fantasy Champ Man', accent: 'cyan' },
    { label: 'Competitions' },
    { label: 'Nations & Clubs' },
    { label: 'Find' },
    { label: 'Game Options', accent: 'yellow' },
];

const cellBorder = 'border border-[#4747a8]/50';

export default function Sidebar({ active }: { active?: string }) {
    return (
        <aside className="flex h-screen w-[190px] shrink-0 flex-col border-r border-black bg-gradient-to-b from-[#00008f] via-[#000048] to-[#00000a] text-white">
            <div
                className={`${cellBorder} border-t-0 border-l-0 px-2 py-3 text-center`}
            >
                <p className="text-sm leading-tight font-bold text-[#f5f000]">
                    Thursday
                </p>
                <p className="text-sm leading-tight font-bold text-[#f5f000]">
                    23.8.01 AM
                </p>
            </div>

            <div className={`${cellBorder} border-t-0 border-l-0 flex`}>
                <button
                    type="button"
                    aria-label="Previous"
                    className="flex flex-1 items-center justify-center border-r border-[#4747a8]/50 py-2 text-[#f5f000] hover:bg-white/10"
                >
                    <ChevronLeft size={20} strokeWidth={3} />
                </button>
                <button
                    type="button"
                    aria-label="Next"
                    className="flex flex-1 items-center justify-center py-2 text-[#f5f000] hover:bg-white/10"
                >
                    <ChevronRight size={20} strokeWidth={3} />
                </button>
            </div>

            <nav className="flex flex-col">
                {navItems.map((item) => (
                    <button
                        key={item.label}
                        type="button"
                        className={`${cellBorder} border-t-0 border-l-0 px-3 py-4 text-center text-[15px] leading-tight font-bold hover:bg-white/10 ${
                            active === item.label
                                ? 'bg-white/10 text-white'
                                : item.accent === 'cyan'
                                  ? 'text-cyan-300'
                                  : item.accent === 'yellow'
                                    ? 'text-[#f5f000]'
                                    : 'text-white'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </nav>

            <div className="flex-1" />
        </aside>
    );
}
