import { Head } from '@inertiajs/react';
import RetroPriceListCard, {
    type TradingItem,
} from '@/components/game/RetroPriceListCard';
import StadiumSubPageHeader from '@/components/game/StadiumSubPageHeader';
import GameLayout from '@/layouts/GameLayout';

const restaurantMenu: TradingItem[] = [
    {
        id: 'margherita-pizza',
        name: 'Margherita Pizza',
        description: "Mario's classic, tomato and mozzarella",
        price: 9.0,
    },
    {
        id: 'pepperoni-pizza',
        name: 'Pepperoni Pizza',
        description: 'Loaded with spicy pepperoni',
        price: 10.5,
    },
    {
        id: 'sweet-sour-chicken',
        name: 'Sweet & Sour Chicken',
        description: 'King Wok house special',
        price: 8.5,
    },
    {
        id: 'kung-pao-beef',
        name: 'Kung Pao Beef',
        description: 'Wok-fried with peanuts and chilli',
        price: 9.5,
    },
    {
        id: 'pad-thai',
        name: 'Pad Thai',
        description: 'Good Thai noodles with prawns',
        price: 8.0,
    },
    {
        id: 'green-curry',
        name: 'Green Curry',
        description: 'Good Thai coconut curry, medium spice',
        price: 9.0,
    },
];

export default function StadiumRestaurants() {
    return (
        <GameLayout active="Nations & Clubs">
            <Head title="AC Milan - Restaurants" />
            <main className="relative flex min-h-screen flex-1 flex-col items-center bg-[#1a1410] p-6 text-white sm:p-10">
                <StadiumSubPageHeader />
                <RetroPriceListCard
                    eyebrow="Matchday Eats"
                    title="Restaurant Menu"
                    items={restaurantMenu}
                />
            </main>
        </GameLayout>
    );
}
