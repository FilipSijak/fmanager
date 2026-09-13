import { Image as ImageIcon } from 'lucide-react';

const menuItems = [
    'File',
    'Stad',
    'Busi',
    'Chair',
    'Data',
    'Trans',
    'Mngr',
    'Squad',
    'Match',
    'Help',
];

export default function TopMenu() {
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

                {menuItems.map((item) => (
                    <button
                        key={item}
                        type="button"
                        className="relative flex w-28 shrink-0 flex-col items-center justify-center gap-2 border-r border-[#2a2f1a] px-3 py-3 hover:bg-[#39ff14]/10"
                    >
                        <span className="text-sm font-bold tracking-wide text-[#f5f000] uppercase [text-shadow:0_0_5px_rgba(245,240,0,0.6)]">
                            {item}
                        </span>
                        <span className="flex h-14 w-full items-center justify-center rounded-sm border border-[#2a2f1a] bg-[#0a0f0a] text-[#4dfaff]">
                            <ImageIcon size={36} />
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );
}
