import type { PaymentMethod } from '../types';
import StepperRow from './StepperRow';
import ToggleButton from './ToggleButton';

export default function PaymentMethodFields({
    paymentMethod,
    lengthYears,
    onPaymentMethodChange,
    onLengthYearsChange,
}: {
    paymentMethod: PaymentMethod;
    lengthYears: number;
    onPaymentMethodChange: (method: PaymentMethod) => void;
    onLengthYearsChange: (years: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3 border-t border-[#1f3a1f] pt-4">
            <div className="grid grid-cols-2 gap-2">
                <ToggleButton
                    label="Cash"
                    active={paymentMethod === 'cash'}
                    onClick={() => onPaymentMethodChange('cash')}
                />
                <ToggleButton
                    label="Mortgage"
                    active={paymentMethod === 'mortgage'}
                    onClick={() => onPaymentMethodChange('mortgage')}
                />
            </div>
            {paymentMethod === 'mortgage' && (
                <StepperRow
                    label="mortgage length"
                    value={`${lengthYears} ${lengthYears === 1 ? 'year' : 'years'}`}
                    onDecrease={() =>
                        onLengthYearsChange(Math.max(2, lengthYears - 1))
                    }
                    onIncrease={() =>
                        onLengthYearsChange(Math.min(12, lengthYears + 1))
                    }
                />
            )}
        </div>
    );
}
