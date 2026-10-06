import { attributeLabel } from '../utils';

export default function AttributeColumn({
    title,
    attributes,
}: {
    title: string;
    attributes: Record<string, number>;
}) {
    return (
        <div className="space-y-1">
            <h3 className="mb-1 text-xs font-bold tracking-widest text-cyan-300 uppercase">
                {title}
            </h3>
            {Object.entries(attributes).map(([key, value]) => (
                <div
                    key={key}
                    className="flex items-center justify-between border-b border-white/10 py-1 text-sm"
                >
                    <span className="font-semibold text-white">
                        {attributeLabel(key)}
                    </span>
                    <span className="font-bold text-[#f5f000]">{value}</span>
                </div>
            ))}
        </div>
    );
}
