import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { logout } from '@/actions/App/Http/Controllers/AuthController';
import { setupGame } from '@/routes';

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

const cellBorder = 'border border-[#2a2f1a]';

export default function Sidebar({ active }: { active?: string }) {
    return (
        <aside
            className="relative flex h-screen w-[190px] shrink-0 flex-col overflow-hidden border-r-2 border-[#f5f000]/40 bg-black text-[#c8ffb0]"
            style={{ fontFamily: "'Courier New', ui-monospace, monospace" }}
        >
            <div
                className="pointer-events-none absolute inset-0 opacity-20"
                style={{
                    backgroundImage:
                        'repeating-linear-gradient(0deg, rgba(255,255,255,0.15) 0px, rgba(255,255,255,0.15) 1px, transparent 1px, transparent 3px)',
                }}
            />

            <div
                className={`${cellBorder} relative border-t-0 border-l-0 px-2 py-3 text-center`}
            >
                <p className="text-sm leading-tight font-bold tracking-wide text-[#f5f000] uppercase [text-shadow:0_0_6px_rgba(245,240,0,0.7)]">
                    Thursday
                </p>
                <p className="text-sm leading-tight font-bold tracking-wide text-[#f5f000] uppercase [text-shadow:0_0_6px_rgba(245,240,0,0.7)]">
                    23.8.01 AM
                </p>
            </div>

            <div
                className={`${cellBorder} relative border-t-0 border-l-0 flex`}
            >
                <button
                    type="button"
                    aria-label="Previous"
                    className="flex flex-1 items-center justify-center border-r border-[#2a2f1a] py-2 text-[#f5f000] hover:bg-[#f5f000]/10"
                >
                    <ChevronLeft size={20} strokeWidth={3} />
                </button>
                <button
                    type="button"
                    aria-label="Next"
                    className="flex flex-1 items-center justify-center py-2 text-[#f5f000] hover:bg-[#f5f000]/10"
                >
                    <ChevronRight size={20} strokeWidth={3} />
                </button>
            </div>

            <nav className="relative flex flex-col">
                {navItems.map((item) => (
                    <button
                        key={item.label}
                        type="button"
                        className={`${cellBorder} border-t-0 border-l-0 px-3 py-4 text-center text-[15px] leading-tight font-bold tracking-wide uppercase hover:bg-[#39ff14]/10 ${
                            active === item.label
                                ? 'bg-[#39ff14]/15 text-[#39ff14] [text-shadow:0_0_8px_rgba(57,255,20,0.9)]'
                                : item.accent === 'cyan'
                                  ? 'text-[#4dfaff] [text-shadow:0_0_5px_rgba(77,250,255,0.6)]'
                                  : item.accent === 'yellow'
                                    ? 'text-[#f5f000] [text-shadow:0_0_5px_rgba(245,240,0,0.6)]'
                                    : 'text-[#c8ffb0]'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </nav>

            <div className="relative flex-1" />
            <Link
                href={setupGame.url()}
                className={`${cellBorder} relative px-3 py-4 text-center font-bold tracking-wide text-[#f5f000] uppercase hover:bg-[#f5f000]/10`}
            >
                My Games
            </Link>
            <Link
                href={logout.url()}
                method="post"
                as="button"
                className={`${cellBorder} relative px-3 py-4 text-center font-bold tracking-wide text-[#c8ffb0] uppercase hover:bg-[#f5f000]/10`}
            >
                Log out
            </Link>
        </aside>
    );
}
