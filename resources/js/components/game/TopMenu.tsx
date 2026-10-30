import { Link, usePage } from '@inertiajs/react';
import { Image as ImageIcon } from 'lucide-react';
import { index as transfers } from '@/actions/App/Http/Controllers/TransferManagementController';
import { finance, squad, stadium } from '@/routes';

type MenuItem = {
    label: string;
    /** Path to a real icon asset (3:2 ratio). Falls back to a mock icon when unset. */
    icon?: string;
    href?: string;
    /** Only available while the user manages a club. */
    clubOnly?: boolean;
};

const menuItems: MenuItem[] = [
    { label: 'File' },
    {
        label: 'Stad',
        clubOnly: true,
        icon: '/game-assets/menu/menu-stadium.png',
        href: stadium.url(),
    },
    {
        label: 'Finance',
        clubOnly: true,
        icon: '/game-assets/menu/menu-finances.png',
        href: finance.url(),
    },
    { label: 'Chair', clubOnly: true },
    { label: 'Data' },
    { label: 'Trans', href: transfers.url(), clubOnly: true },
    { label: 'Mngr' },
    { label: 'Squad', href: squad.url(), clubOnly: true },
    { label: 'Match', clubOnly: true },
    { label: 'Help' },
];

export default function TopMenu() {
    const { game } = usePage<{ game: { has_club: boolean } | null }>().props;
    const hasClub = game?.has_club ?? true;
    const visibleMenuItems = menuItems.filter(
        (item) => hasClub || !item.clubOnly,
    );

    return (
        <div
            className="group absolute inset-x-0 top-0 z-50 -translate-y-1/2 transition-transform duration-300 ease-out hover:translate-y-0 focus-within:translate-y-0"
            style={{ fontFamily: "'Courier New', ui-monospace, monospace" }}
        >
            <div className="relative flex items-stretch overflow-x-auto border-b-2 border-[#f5f000]/40 bg-black shadow-[0_8px_24px_rgba(0,0,0,0.6)]">
                <div
                    className="pointer-events-none absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'repeating-linear-gradient(0deg, rgba(255,255,255,0.15) 0px, rgba(255,255,255,0.15) 1px, transparent 1px, transparent 3px)',
                    }}
                />

                {visibleMenuItems.map((item) => {
                    const itemContent = (
                        <>
                            <span className="text-sm font-bold tracking-wide text-[#f5f000] uppercase [text-shadow:0_0_5px_rgba(245,240,0,0.6)]">
                                {item.label}
                            </span>
                            <span
                                className="flex w-full items-center justify-center overflow-hidden rounded-sm border border-[#2a2f1a] bg-[#0a0f0a] text-[#4dfaff]"
                                style={{ aspectRatio: '3 / 2' }}
                            >
                                {item.icon ? (
                                    <img
                                        src={item.icon}
                                        alt=""
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <ImageIcon size={36} />
                                )}
                            </span>
                        </>
                    );

                    const className =
                        'relative flex w-28 shrink-0 flex-col items-center justify-center gap-2 border-r border-[#2a2f1a] px-3 py-3 hover:bg-[#39ff14]/10';

                    return item.href ? (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={className}
                        >
                            {itemContent}
                        </Link>
                    ) : (
                        <button
                            key={item.label}
                            type="button"
                            className={className}
                        >
                            {itemContent}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
