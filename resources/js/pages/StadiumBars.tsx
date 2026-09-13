import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

type MenuItem = {
    id: string;
    name: string;
    description: string;
    price: number;
};

const initialMenu: MenuItem[] = [
    {
        id: 'draft-lager',
        name: 'Draft Lager',
        description: 'Cold one, on tap',
        price: 4.5,
    },
    {
        id: 'craft-ipa',
        name: 'Craft IPA',
        description: 'Hoppy and bitter',
        price: 5.5,
    },
    {
        id: 'house-wine',
        name: 'House Wine',
        description: 'Red or white',
        price: 6.0,
    },
    {
        id: 'whiskey-shot',
        name: 'Whiskey Shot',
        description: 'Neat or on the rocks',
        price: 7.0,
    },
    {
        id: 'cocktail',
        name: "Manager's Cocktail",
        description: 'House special mix',
        price: 8.5,
    },
    {
        id: 'soda',
        name: 'Soft Drink',
        description: 'Cola, lemonade or soda water',
        price: 2.5,
    },
];

function formatPrice(price: number): string {
    return price.toFixed(2);
}

export default function StadiumBars() {
    const [menu, setMenu] = useState<MenuItem[]>(initialMenu);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [draftPrice, setDraftPrice] = useState('');
    const priceInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editingId) {
            priceInputRef.current?.focus();
        }
    }, [editingId]);

    const startEditing = (item: MenuItem) => {
        setEditingId(item.id);
        setDraftPrice(formatPrice(item.price));
    };

    const cancelEditing = () => {
        setEditingId(null);
        setDraftPrice('');
    };

    const savePrice = (id: string) => {
        const parsed = Number.parseFloat(draftPrice);
        if (Number.isFinite(parsed) && parsed >= 0) {
            setMenu((current) =>
                current.map((item) =>
                    item.id === id ? { ...item, price: parsed } : item,
                ),
            );
        }
        cancelEditing();
    };

    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Bars" />
            <main className="relative flex min-h-screen flex-1 flex-col items-center bg-[#1a1410] p-6 text-white sm:p-10">
                <StadiumSubPageHeader />
                <section
                    className="w-full max-w-2xl border-4 border-[#3a2418] bg-[#241a12] p-6 shadow-[0_0_0_2px_#c9a24b,0_10px_30px_rgba(0,0,0,0.6)] sm:p-10"
                    style={{
                        fontFamily: "'Courier New', ui-monospace, monospace",
                    }}
                >
                    <header className="mb-6 border-b-4 border-dashed border-[#c9a24b]/60 pb-4 text-center">
                        <p className="text-xs tracking-[0.3em] text-[#c9a24b] uppercase">
                            The Tavern
                        </p>
                        <h1 className="mt-1 text-3xl font-bold tracking-widest text-[#f5e6c8] uppercase">
                            Bar Menu
                        </h1>
                        <p className="mt-1 text-xs tracking-[0.2em] text-[#8a7358] uppercase">
                            Prices in $ - Click a price to edit
                        </p>
                    </header>

                    <ul className="flex flex-col gap-1">
                        {menu.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-center justify-between gap-4 border-b border-dotted border-[#8a7358]/50 py-3 last:border-b-0"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-lg font-bold tracking-wide text-[#f5e6c8] uppercase">
                                        {item.name}
                                    </p>
                                    <p className="truncate text-xs text-[#8a7358] italic">
                                        {item.description}
                                    </p>
                                </div>

                                {editingId === item.id ? (
                                    <div className="flex shrink-0 items-center gap-2">
                                        <span className="text-lg font-bold text-[#c9a24b]">
                                            $
                                        </span>
                                        <input
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            ref={priceInputRef}
                                            value={draftPrice}
                                            onChange={(e) =>
                                                setDraftPrice(e.target.value)
                                            }
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    savePrice(item.id);
                                                } else if (e.key === 'Escape') {
                                                    cancelEditing();
                                                }
                                            }}
                                            className="w-20 border-2 border-[#c9a24b] bg-[#1a1410] px-2 py-1 text-right text-lg font-bold text-[#f5e6c8] focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => savePrice(item.id)}
                                            className="cursor-pointer border-2 border-[#c9a24b] bg-[#c9a24b] px-2 py-1 text-xs font-bold tracking-wide text-[#241a12] uppercase hover:bg-[#e0bb63]"
                                        >
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            onClick={cancelEditing}
                                            className="cursor-pointer border-2 border-[#8a7358] px-2 py-1 text-xs font-bold tracking-wide text-[#8a7358] uppercase hover:bg-white/5"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => startEditing(item)}
                                        className="group flex shrink-0 cursor-pointer items-center gap-2 text-lg font-bold text-[#c9a24b] hover:text-[#e0bb63]"
                                    >
                                        <span className="tracking-wider">
                                            ${formatPrice(item.price)}
                                        </span>
                                        <Pencil className="size-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </main>
        </GameLayout>
    );
}
