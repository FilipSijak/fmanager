import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { stadium } from '@/routes';

export default function StadiumSubPageHeader() {
    return (
        <Link
            href={stadium.url()}
            title="Back to stadium"
            aria-label="Back to stadium"
            className="absolute top-4 right-4 z-10 flex size-9 items-center justify-center rounded border border-[#4a5662] bg-[#202831] text-[#d0d7de] hover:bg-white/10"
        >
            <X className="size-5" />
        </Link>
    );
}
