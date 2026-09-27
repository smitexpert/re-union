import { useEffect, useState } from 'react';
import { formatBDT } from '@/components/registration/utils/format';

export default function Step4Review({ data, setData, errors, reunion, feeBreakdown }) {
    const update = (field) => (e) => setData(field, e.target.value);
    const adultCount = Number(data.guest_count_adult || 0);
    const childCount = Number(data.guest_count_child || 0);
    const perHead = feeBreakdown.perHead || {};
    const total =
        Number(feeBreakdown.base || 0) +
        (perHead.Adult ? perHead.Adult * adultCount : 0) +
        (perHead.Child ? perHead.Child * childCount : 0);

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

            {errors.form ? (
                <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    {errors.form}
                </p>
            ) : null}
        </div>
    );
}
