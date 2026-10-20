import { Link } from '@inertiajs/react';
import { show } from '@/routes/clubs';

export default function ClubLink({
    clubId,
    name,
    seasonId,
    className = '',
}: {
    clubId: number | null;
    name: string | null;
    seasonId?: number;
    className?: string;
}) {
    if (clubId === null)
        return <span className={className}>{name ?? 'To be decided'}</span>;
    return (
        <Link
            href={show.url(clubId, { query: { season_id: seasonId } })}
            className={`hover:underline underline-offset-4 ${className}`}
        >
            {name ?? 'Unknown club'}
        </Link>
    );
}
