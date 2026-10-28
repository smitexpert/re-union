import NumberStepper from '@/components/registration/fields/NumberStepper';
import { formatBDT } from '@/components/registration/utils/format';
import {
    computeFeeLines,
    computeServiceCharge,
    serviceChargeLabel,
} from '@/components/registration/utils/fee';

export default function Step3Guests({ data, setData, errors, feeBreakdown }) {
    const adultCount = Number(data.guest_count_adult || 0);
    const childCount = Number(data.guest_count_child || 0);
    const feeLines = computeFeeLines(feeBreakdown, adultCount, childCount);
    const subtotal = feeLines.reduce((sum, line) => sum + line.amount, 0);
    const chargeAmount = computeServiceCharge(feeBreakdown, subtotal);
    const chargeLabel = serviceChargeLabel(feeBreakdown);
    const total = subtotal + chargeAmount;
    const perHead = feeBreakdown.perHead || {};

    return (
        <div className="space-y-4">
            <h2 id="step-3-heading" className="text-xl font-bold text-gray-900">
                Guests
            </h2>
            <p className="text-sm text-gray-600">
                Add the number of guests you're bringing.
            </p>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <NumberStepper
                        id="guest_count_adult"
                        label="Adults"
                        required
                        value={data.guest_count_adult}
                        onChange={(value) => setData('guest_count_adult', value)}
                        error={errors.guest_count_adult}
                    />
                    {perHead.Adult ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Adult)} per adult</p>
                    ) : null}
                </div>
                <div>
                    <NumberStepper
                        id="guest_count_child"
                        label="Children"
                        required
                        value={data.guest_count_child}
                        onChange={(value) => setData('guest_count_child', value)}
                        error={errors.guest_count_child}
                    />
                    {perHead.Child ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Child)} per child (3 to 5 years)</p>
                    ) : null}
                </div>
            </div>

            <div className="space-y-1 rounded-2xl bg-brand/10 px-4 py-3 text-sm font-semibold text-brand-ink">
                {feeLines.map((line) => (
                    <div key={line.key} className="flex justify-between">
                        <span>{line.label}</span>
                        <span>{formatBDT(line.amount)}</span>
                    </div>
                ))}
                <div className="flex justify-between border-t border-brand/30 pt-1">
                    <span>Subtotal</span>
                    <span>{formatBDT(subtotal)}</span>
                </div>
                {chargeLabel ? (
                    <div className="flex justify-between">
                        <span>{chargeLabel}</span>
                        <span>{formatBDT(chargeAmount)}</span>
                    </div>
                ) : null}
                <div className="flex justify-between border-t border-brand/30 pt-1 text-base">
                    <span>Estimated total</span>
                    <span>{formatBDT(total)}</span>
                </div>
            </div>
        </div>
    );
}
