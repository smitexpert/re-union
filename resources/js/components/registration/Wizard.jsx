import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Stepper from '@/components/registration/Stepper';
import Step1Student from '@/components/registration/Step1Student';
import Step2Reunion from '@/components/registration/Step2Reunion';
import Step3Guests from '@/components/registration/Step3Guests';
import Step4Review from '@/components/registration/Step4Review';

const INITIAL_DATA = {
    student: {
        full_name: '',
        phone: '',
        graduation_year: '',
        department: '',
        session: '',
        current_occupation: '',
        present_address: '',
        permanent_address: '',
        office_address: '',
        photo: null,
    },
    tshirt_size: '',
    guest_count_adult: 0,
    guest_count_child: 0,
    notes: '',
};

function isStep1Valid(data) {
    const s = data.student;
    return (
        // A picked photo must be a File instance — null/empty strings mean
        // the user hasn't chosen one yet, so the Continue button stays
        // disabled until they do.
        s.photo instanceof File &&
        s.full_name.trim() !== '' &&
        s.phone.trim() !== '' &&
        String(s.graduation_year).trim() !== '' &&
        s.department.trim() !== '' &&
        s.session.trim() !== '' &&
        s.current_occupation.trim() !== '' &&
        s.present_address.trim() !== '' &&
        String(data.tshirt_size).trim() !== ''
    );
}

/**
 * Flatten the wizard's nested data into a FormData instance so the photo file
 * uploads intact. Laravel's validator expects dotted keys like `student.name`
 * for nested rules, so we mirror the same shape here.
 */
function buildFormData(data) {
    const fd = new FormData();
    for (const [key, value] of Object.entries(data)) {
        if (value !== null && typeof value === 'object' && !(value instanceof File)) {
            for (const [subKey, subValue] of Object.entries(value)) {
                if (subValue === null || subValue === undefined) continue;
                fd.append(`${key}[${subKey}]`, subValue);
            }
        } else if (value !== null && value !== undefined) {
            fd.append(key, value);
        }
    }
    return fd;
}

export default function Wizard({ reunion, feeBreakdown }) {
    const [step, setStep] = useState(0);
    const [data, setData] = useState(INITIAL_DATA);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const setField = (path, value) => {
        setData((prev) => {
            if (!path.includes('.')) return { ...prev, [path]: value };
            const [head, key] = path.split('.');
            return { ...prev, [head]: { ...prev[head], [key]: value } };
        });
    };

    const onBack = () => setStep((s) => Math.max(0, s - 1));
    const onNext = () => setStep((s) => Math.min(3, s + 1));

    // When the user advances to the Review step, give the DOM a tick to
    // settle before the Submit button is interactive. Without this, a quick
    // double-tap on the Continue button can land on the Submit button that
    // replaces it at the same DOM position, accidentally triggering the
    // form submission and the auto-redirect to /payments/{code}.
    const [justAdvanced, setJustAdvanced] = useState(false);
    useEffect(() => {
        if (step === 3) {
            setJustAdvanced(true);
            const t = setTimeout(() => setJustAdvanced(false), 500);
            return () => clearTimeout(t);
        }
        return undefined;
    }, [step]);

    const onSubmit = (e) => {
        if (e) e.preventDefault();
        setProcessing(true);

        // When a photo is attached, we have to send multipart/form-data so the
        // file reaches the server intact. Inertia will detect the FormData and
        // post it accordingly.
        const hasFile = data.student.photo instanceof File;
        const payload = hasFile ? buildFormData(data) : data;

        router.post('/register', payload, {
            forceFormData: hasFile,
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                setErrors({});
            },
            onError: (errs) => {
                console.log('onError', errs);
                const fieldErrors = errs || {};
                setErrors(fieldErrors);
                setProcessing(false);

                // Jump to the earliest step that has errors so the user sees
                // the inline error messages instead of a silent no-op on
                // Step 4.
                const hasStudentError = Object.keys(fieldErrors).some(
                    (k) => k.startsWith('student.') || k === 'tshirt_size',
                );
                if (hasStudentError) {
                    setStep(0);
                } else {
                    const hasGuestError = Object.keys(fieldErrors).some(
                        (k) => k === 'guest_count_adult' || k === 'guest_count_child' || k === 'notes',
                    );
                    if (hasGuestError) {
                        setStep(2);
                    } else if (Object.keys(fieldErrors).length > 0) {
                        setStep(0);
                    }
                }
            },
        });
    };

    const step1Ok = isStep1Valid(data);
    const canAdvance = step === 0 ? step1Ok : true;

    return (
        <section
            aria-labelledby={`step-${step + 1}-heading`}
            className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-8"
        >
            <Stepper current={step} />

            <form onSubmit={onSubmit}>
                {step === 0 ? (
                    <Step1Student data={data} setData={setField} errors={errors} />
                ) : null}
                {step === 1 ? (
                    <Step2Reunion reunion={reunion} feeBreakdown={feeBreakdown} />
                ) : null}
                {step === 2 ? (
                    <Step3Guests data={data} setData={setField} errors={errors} feeBreakdown={feeBreakdown} />
                ) : null}
                {step === 3 ? (
                    <Step4Review
                        data={data}
                        setData={setField}
                        errors={errors}
                        reunion={reunion}
                        feeBreakdown={feeBreakdown}
                    />
                ) : null}

                <div className="sticky bottom-0 -mx-5 mt-6 flex items-center justify-between gap-3 border-t border-gray-100 bg-white/95 px-5 py-3 backdrop-blur sm:static sm:mx-0 sm:mt-6 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                    {step > 0 ? (
                        <button
                            type="button"
                            onClick={onBack}
                            className="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-mist focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            Back
                        </button>
                    ) : (
                        <span />
                    )}
                    {step < 3 ? (
                        <button
                            type="button"
                            onClick={onNext}
                            disabled={!canAdvance}
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            Continue
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => onSubmit()}
                            disabled={processing || justAdvanced}
                            aria-busy={processing ? 'true' : 'false'}
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            {processing ? 'Submitting…' : 'Submit registration'}
                        </button>
                    )}
                </div>
            </form>
        </section>
    );
}
