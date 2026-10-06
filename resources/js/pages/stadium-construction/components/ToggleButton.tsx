export default function ToggleButton({
    label,
    active,
    onClick,
    disabled,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={`w-full cursor-pointer border px-4 py-1.5 text-sm font-bold tracking-wide uppercase transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
                active
                    ? 'border-[#39ff14]/60 bg-[#0f2a0f] text-[#39ff14] [text-shadow:0_0_5px_rgba(57,255,20,0.6)]'
                    : 'border-[#1f3a1f] bg-[#020a05] text-[#5fae5f] hover:border-[#39ff14]/40'
            }`}
        >
            {label}
        </button>
    );
}
