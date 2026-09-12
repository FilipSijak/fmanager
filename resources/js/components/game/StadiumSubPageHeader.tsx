import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { stadium } from '@/routes';

export default function StadiumSubPageHeader({ title }: { title: string }) {
    return (
        <header className="flex items-center justify-between border-b border-black bg-[#202831] px-5 py-3">
            <div>
                <p className="text-xs font-bold tracking-[0.25em] text-[#f5f000] uppercase">
                    Stadium
                </p>
                <h1 className="text-2xl font-black">{title}</h1>
            </div>
            <Link
                href={stadium.url()}
                title="Back to stadium"
                aria-label="Back to stadium"
                className="flex size-9 items-center justify-center rounded border border-[#4a5662] text-[#d0d7de] hover:bg-white/10"
            >
                <X className="size-5" />
            </Link>
        </header>
    );
}
