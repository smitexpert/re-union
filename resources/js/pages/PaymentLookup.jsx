import { Head, useForm } from '@inertiajs/react';
import PageLogoHeader from '@/components/PageLogoHeader';

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2';

export default function PaymentLookup({ flash }) {
    const form = useForm({
        identifier: '',
    });

    const submit = (e) => {
        e.preventDefault();
        const value = String(form.data.identifier || '').trim();
        if (!value) return;
        // Normalize the registration code: strip whitespace and uppercase the
        // parts separated by the dash (e.g. `swa-ab12cd34` -> `SWA-AB12CD34`).
        const normalized = value.replace(/\s+/g, '').toUpperCase();
        window.location.href = `/payments/${encodeURIComponent(normalized)}`;
    };

    return (
        <>
            <Head title="Find my registration — SWAATC" />
            <PageLogoHeader />
            <main className="min-h-screen bg-mist px-4 py-12 sm:py-16">
                <div className="mx-auto max-w-md space-y-6">
                    {flash?.status ? (
                        <p
                            className="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                            role="status"
                        >
                            {flash.status}
                        </p>
                    ) : null}

                    <section
                        aria-labelledby="lookup-heading"
                        className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div
                            aria-hidden="true"
                            className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand/10 text-brand-ink"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className="h-7 w-7"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                                />
                            </svg>
                        </div>

                        <h1
                            id="lookup-heading"
                            className="text-center text-xl font-bold text-gray-900 sm:text-2xl"
                        >
                            Find my registration
                        </h1>
                        <p className="mt-2 text-center text-sm text-gray-600">
                            Enter your registration code to view payment status and
                            download your ticket.
                        </p>

                        <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
                            <div>
                                <label
                                    htmlFor="identifier"
                                    className="mb-1 block text-sm font-semibold text-gray-700"
                                >
                                    Registration code
                                </label>
                                <input
                                    id="identifier"
                                    type="text"
                                    inputMode="text"
                                    autoComplete="off"
                                    required
                                    value={form.data.identifier}
                                    onChange={(e) =>
                                        form.setData('identifier', e.target.value)
                                    }
                                    placeholder="e.g. SWA-AB12CD34"
                                    className={`block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${focusRing}`}
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing || !form.data.identifier.trim()}
                                aria-busy={form.processing ? 'true' : 'false'}
                                className={`inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 ${focusRing}`}
                            >
                                {form.processing ? 'Looking up…' : 'View status'}
                            </button>
                        </form>
                    </section>

                    <p className="text-center text-xs text-gray-500">
                        Lost your code? Check your registration confirmation SMS/email, or
                        contact the reunion committee.
                    </p>
                </div>
            </main>
        </>
    );
}
