import { type ContractTerms, fieldClass } from './types';

const labels: Record<keyof ContractTerms, string> = {
    salary: 'Weekly salary',
    transfer_fee: 'Signing fee',
    appearance: 'Appearance bonus',
    goal: 'Goal bonus',
    assist: 'Assist bonus',
    clean_sheet: 'Clean sheet bonus',
    league: 'League title bonus',
    promotion: 'Promotion bonus',
    cup: 'Cup win bonus',
    el: 'Europa League bonus',
    cl: 'Champions League bonus',
};

export default function ContractFields({
    terms,
    onChange,
    disabled = false,
}: {
    terms: ContractTerms;
    onChange: (terms: ContractTerms) => void;
    disabled?: boolean;
}) {
    return (
        <fieldset
            disabled={disabled}
            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <legend className="mb-4 text-lg font-bold text-cyan-300">
                Player contract
            </legend>
            {(Object.keys(labels) as (keyof ContractTerms)[]).map((field) => (
                <label
                    key={field}
                    className="flex flex-col gap-2 text-sm text-slate-300"
                >
                    {labels[field]} (£)
                    <input
                        type="number"
                        name={field}
                        min={field === 'salary' ? 1 : 0}
                        max={field === 'transfer_fee' ? 2000000000 : 100000000}
                        step="1"
                        required
                        value={terms[field]}
                        onChange={(event) =>
                            onChange({
                                ...terms,
                                [field]: Number(event.target.value),
                            })
                        }
                        className={fieldClass}
                    />
                </label>
            ))}
        </fieldset>
    );
}
