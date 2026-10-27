import { Head, Link } from '@inertiajs/react';
import PageLogoHeader from '@/components/PageLogoHeader';

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2';

export default function NotFound({ reason, attemptedCode, paymentLookupUrl }) {
    const isRegistrationMiss = reason === 'registration_not_found';

    return (
        <>
            <Head title="Not found — SWAATC" />
            <PageLogoHeader />
            <main className="min-h-screen bg-mist px-4 py-12 sm:py-16">
                <div className="mx-auto max-w-xl space-y-6">
                    <section
                        aria-labelledby="not-found-heading"
                        className="rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-sm sm:p-8"
                    >
                        <div
                            aria-hidden="true"
                            className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10 text-brand-ink"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className="h-9 w-9"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                                />
                            </svg>
                        </div>

                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-ink">
                            404
                        </p>
                        <h1
                            id="not-found-heading"
                            className="mt-2 text-balance text-2xl font-bold text-gray-900 sm:text-3xl"
                        >
                            {isRegistrationMiss
                                ? 'Registration not found'
                                : 'Page not found'}
                        </h1>
                        <p className="mt-3 text-sm text-gray-600 sm:text-base">
                            {isRegistrationMiss ? (
                                <>
                                    We couldn&rsquo;t find a registration for{' '}
                                    <span className="break-all font-mono font-semibold text-gray-900">
                                        {attemptedCode || 'that code'}
                                    </span>
                                    . Double-check the code on your registration
                                    receipt.
                                </>
                            ) : (
                                <>
                                    The page you&rsquo;re looking for doesn&rsquo;t exist or
                                    may have moved.
                                </>
                            )}
                        </p>
                    </section>

                    <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-center">
                        <Link
                            href={paymentLookupUrl || '/payments'}
                            className={`inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 sm:w-auto ${focusRing}`}
                        >
                            Find my registration
                        </Link>
                        <Link
                            href="/"
                            className={`inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition duration-200 hover:bg-mist sm:w-auto ${focusRing}`}
                        >
                            Back to home
                        </Link>
                    </div>
                </div>
            </main>
        </>
    );
}
