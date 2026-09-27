import { formatBDT } from '@/components/registration/utils/format';

export default function Step2Reunion({ reunion, feeBreakdown }) {
    const perHead = feeBreakdown.perHead || {};
    return (
        <div className="space-y-4">
            <h2 id="step-2-heading" className="text-xl font-bold text-gray-900">
                Reunion details
            </h2>
            <p className="text-sm text-gray-600">
                Confirm the event and review the fee structure.
            </p>

            <dl className="space-y-2 rounded-2xl border border-gray-100 bg-mist p-4 text-sm">
                <div className="flex justify-between">
                    <dt className="font-semibold text-gray-700">Title</dt>
                    <dd className="text-gray-900">{reunion.title}</dd>
                </div>
                <div className="flex justify-between">
                    <dt className="font-semibold text-gray-700">Event date</dt>
                    <dd className="text-gray-900">{reunion.event_date}</dd>
                </div>
                {reunion.registration_deadline ? (
                    <div className="flex justify-between">
                        <dt className="font-semibold text-gray-700">Registration deadline</dt>
                        <dd className="text-gray-900">{reunion.registration_deadline}</dd>
                    </div>
                ) : null}
                <div className="flex justify-between">
                    <dt className="font-semibold text-gray-700">Base registration fee</dt>
                    <dd className="text-gray-900">{formatBDT(feeBreakdown.base)}</dd>
                </div>
                {perHead.Adult ? (
                    <div className="flex justify-between">
                        <dt className="font-semibold text-gray-700">Adult guest (per head)</dt>
                        <dd className="text-gray-900">{formatBDT(perHead.Adult)}</dd>
                    </div>
                ) : null}
                {perHead.Child ? (
                    <div className="flex justify-between">
                        <dt className="font-semibold text-gray-700">Child guest (per head)</dt>
                        <dd className="text-gray-900">{formatBDT(perHead.Child)}</dd>
                    </div>
                ) : null}
            </dl>

            <p className="rounded-2xl bg-brand/10 px-4 py-3 text-sm text-brand-ink">
                The final total (registration fee + per-guest fees) is computed automatically on the review step.
            </p>
        </div>
    );
}
