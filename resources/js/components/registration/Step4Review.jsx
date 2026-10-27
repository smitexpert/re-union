import { useEffect, useState } from 'react';
import { formatBDT } from '@/components/registration/utils/format';
import {
    computeFeeLines,
    computeServiceCharge,
    serviceChargeLabel,
} from '@/components/registration/utils/fee';

export default function Step4Review({ data, setData, errors, reunion, feeBreakdown }) {
    const update = (field) => (e) => setData(field, e.target.value);
    const adultCount = Number(data.guest_count_adult || 0);
    const childCount = Number(data.guest_count_child || 0);
    const feeLines = computeFeeLines(feeBreakdown, adultCount, childCount);
    const subtotal = feeLines.reduce((sum, line) => sum + line.amount, 0);
    const chargeAmount = computeServiceCharge(feeBreakdown, subtotal);
    const chargeLabel = serviceChargeLabel(feeBreakdown);
    const total = subtotal + chargeAmount;

    // Generate a fresh object URL for the picked photo so we can render it on
    // this read-only review screen without exposing the File object directly.
    const [photoPreviewUrl, setPhotoPreviewUrl] = useState(null);
    useEffect(() => {
        if (data.student.photo instanceof File) {
            const url = URL.createObjectURL(data.student.photo);
            setPhotoPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        }
        setPhotoPreviewUrl(null);
        return undefined;
    }, [data.student.photo]);

    return (
        <div className="space-y-5">
            <h2 id="step-4-heading" className="text-xl font-bold text-gray-900">
                Review & submit
            </h2>

            {photoPreviewUrl ? (
                <div className="flex items-center gap-3 rounded-2xl border border-gray-100 bg-mist p-4">
                    <img
                        src={photoPreviewUrl}
                        alt="Your photo preview"
                        className="h-16 w-16 shrink-0 rounded-full object-cover ring-1 ring-gray-200"
                    />
                    <div className="text-sm">
                        <div className="font-semibold text-gray-700">Photo attached</div>
                        <div className="text-gray-500">Will be uploaded with your registration.</div>
                    </div>
                </div>
            ) : null}

            <dl className="space-y-2 rounded-2xl border border-gray-100 bg-mist p-4 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Name</dt>
                    <dd className="text-right text-gray-900">{data.student.full_name}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Phone</dt>
                    <dd className="text-right text-gray-900">{data.student.phone}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Graduation year</dt>
                    <dd className="text-right text-gray-900">{data.student.graduation_year}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Department</dt>
                    <dd className="text-right text-gray-900">{data.student.department}</dd>
                </div>
                {data.student.session ? (
                    <div className="flex justify-between gap-3">
                        <dt className="font-semibold text-gray-700">Session</dt>
                        <dd className="text-right text-gray-900">{data.student.session}</dd>
                    </div>
                ) : null}
            </dl>

            <dl className="space-y-2 rounded-2xl border border-gray-100 bg-mist p-4 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Reunion</dt>
                    <dd className="text-right text-gray-900">{reunion.title}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Event date</dt>
                    <dd className="text-right text-gray-900">{reunion.event_date}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Adult guests</dt>
                    <dd className="text-right text-gray-900">{adultCount}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Child guests</dt>
                    <dd className="text-right text-gray-900">{childCount}</dd>
                </div>
                {data.tshirt_size ? (
                    <div className="flex justify-between gap-3">
                        <dt className="font-semibold text-gray-700">T-shirt size</dt>
                        <dd className="text-right text-gray-900">{data.tshirt_size}</dd>
                    </div>
                ) : null}
                {feeLines.map((line) => (
                    <div key={line.key} className="flex justify-between gap-3">
                        <dt className="font-semibold text-gray-700">{line.label}</dt>
                        <dd className="text-right text-gray-900">{formatBDT(line.amount)}</dd>
                    </div>
                ))}
                <div className="flex justify-between gap-3 border-t border-gray-200 pt-2">
                    <dt className="font-semibold text-gray-700">Subtotal</dt>
                    <dd className="text-right text-gray-900">{formatBDT(subtotal)}</dd>
                </div>
                {chargeLabel ? (
                    <div className="flex justify-between gap-3">
                        <dt className="font-semibold text-gray-700">{chargeLabel}</dt>
                        <dd className="text-right text-gray-900">{formatBDT(chargeAmount)}</dd>
                    </div>
                ) : null}
                <div className="flex justify-between gap-3 border-t border-gray-200 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd>{formatBDT(total)}</dd>
                </div>
            </dl>

            <div>
                <label htmlFor="notes" className="mb-1 block text-sm font-semibold text-gray-700">
                    Notes (optional)
                </label>
                <textarea
                    id="notes"
                    name="notes"
                    rows={3}
                    value={data.notes}
                    onChange={update('notes')}
                    className="block w-full rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                />
            </div>

            <section
                aria-labelledby="payment-instructions-heading"
                className="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-gray-800"
            >
                <h3
                    id="payment-instructions-heading"
                    className="text-base font-bold text-gray-900"
                >
                    Payment instructions
                </h3>

                <p className="text-sm">
                    Please pay the <span className="font-semibold">total amount</span> shown
                    above via <span className="font-semibold">bKash</span> or{' '}
                    <span className="font-semibold">Nagad</span> to the number below before
                    submitting your registration.
                </p>

                <div className="rounded-xl border border-amber-300 bg-white px-3 py-2 text-center">
                    <div className="text-xs uppercase tracking-wide text-gray-500">
                        Payment number
                    </div>
                    <div className="text-lg font-bold tracking-wide text-gray-900">
                        01735997767
                    </div>
                </div>

                <details className="rounded-xl border border-amber-200 bg-white p-3">
                    <summary className="cursor-pointer text-sm font-semibold text-gray-900">
                        How to pay with bKash
                    </summary>
                    <ol className="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-700">
                        <li>Open the bKash app or dial <span className="font-semibold">*247#</span>.</li>
                        <li>Choose <span className="font-semibold">"Send Money"</span>.</li>
                        <li>
                            Enter the number:{' '}
                            <span className="font-semibold">01735997767</span>.
                        </li>
                        <li>
                            Enter the <span className="font-semibold">total amount</span> shown
                            above.
                        </li>
                        <li>
                            Enter a reference (e.g. your{' '}
                            <span className="font-semibold">full name</span> or{' '}
                            <span className="font-semibold">graduation year</span>).
                        </li>
                        <li>
                            Enter your bKash <span className="font-semibold">PIN</span> and
                            confirm.
                        </li>
                        <li>Save the transaction ID (TrxID) for your records.</li>
                    </ol>
                </details>

                <details className="rounded-xl border border-amber-200 bg-white p-3">
                    <summary className="cursor-pointer text-sm font-semibold text-gray-900">
                        How to pay with Nagad
                    </summary>
                    <ol className="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-700">
                        <li>Open the Nagad app or dial <span className="font-semibold">*167#</span>.</li>
                        <li>Choose <span className="font-semibold">"Send Money"</span>.</li>
                        <li>
                            Enter the number:{' '}
                            <span className="font-semibold">01735997767</span>.
                        </li>
                        <li>
                            Enter the <span className="font-semibold">total amount</span> shown
                            above.
                        </li>
                        <li>
                            Enter a reference (e.g. your{' '}
                            <span className="font-semibold">full name</span> or{' '}
                            <span className="font-semibold">graduation year</span>).
                        </li>
                        <li>
                            Enter your Nagad <span className="font-semibold">PIN</span> and
                            confirm.
                        </li>
                        <li>Save the transaction ID (TrxID) for your records.</li>
                    </ol>
                </details>

                <p className="text-xs text-gray-600">
                    After completing payment, please{' '}
                    <span className="font-semibold">save your transaction ID (TrxID)</span> —
                    you may be asked to share it for verification.
                </p>
            </section>

            {errors.form ? (
                <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    {errors.form}
                </p>
            ) : null}
        </div>
    );
}
