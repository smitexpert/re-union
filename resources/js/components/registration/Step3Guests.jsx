import { formatBDT } from '@/components/registration/utils/format';

export default function Step3Guests({ data, setData, errors, feeBreakdown }) {
    const update = (field) => (e) => setData(field, e.target.value);
    const adultCount = Number(data.guest_count_adult || 0);
    const childCount = Number(data.guest_count_child || 0);
    const perHead = feeBreakdown.perHead || {};
    const total =
        Number(feeBreakdown.base || 0) +
        (perHead.Adult ? perHead.Adult * adultCount : 0) +
        (perHead.Child ? perHead.Child * childCount : 0);

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
                    <label htmlFor="guest_count_adult" className="mb-1 block text-sm font-semibold text-gray-700">
                        Adults <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="guest_count_adult"
                        name="guest_count_adult"
                        type="number"
                        min="0"
                        inputMode="numeric"
                        value={data.guest_count_adult}
                        onChange={update('guest_count_adult')}
                        aria-invalid={errors.guest_count_adult ? 'true' : 'false'}
                        className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                            errors.guest_count_adult ? 'border-red-400' : 'border-gray-200'
                        }`}
                    />
                    {errors.guest_count_adult ? (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_count_adult}</p>
                    ) : null}
                    {perHead.Adult ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Adult)} per adult</p>
                    ) : null}
                </div>
                <div>
                    <label htmlFor="guest_count_child" className="mb-1 block text-sm font-semibold text-gray-700">
                        Children <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="guest_count_child"
                        name="guest_count_child"
                        type="number"
                        min="0"
                        inputMode="numeric"
                        value={data.guest_count_child}
                        onChange={update('guest_count_child')}
                        aria-invalid={errors.guest_count_child ? 'true' : 'false'}
                        className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                            errors.guest_count_child ? 'border-red-400' : 'border-gray-200'
                        }`}
                    />
                    {errors.guest_count_child ? (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_count_child}</p>
                    ) : null}
                    {perHead.Child ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Child)} per child</p>
                    ) : null}
                </div>
            </div>

            <div className="rounded-2xl bg-brand/10 px-4 py-3 text-sm font-semibold text-brand-ink">
                Estimated total: {formatBDT(total)}
            </div>
        </div>
    );
}
