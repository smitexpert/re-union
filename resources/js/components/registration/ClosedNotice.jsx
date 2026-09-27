import { Link } from '@inertiajs/react';

export default function ClosedNotice({ banglaMessage, paymentLookupUrl }) {
    return (
        <section
            aria-labelledby="closed-notice-heading"
            className="rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-sm sm:p-8"
        >
            <div
                aria-hidden="true"
                className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10 text-brand-ink"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-8 w-8">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h1 id="closed-notice-heading" className="text-balance text-xl font-bold text-gray-900 sm:text-2xl">
                {banglaMessage}
            </h1>
            <p className="mt-3 text-sm text-gray-600">
                New events will be announced here. Already have a registration code? You can pay for it directly.
            </p>
            <div className="mt-6 flex flex-col items-center gap-3">
                <Link
                    href={paymentLookupUrl}
                    className="inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    Pay with registration code
                </Link>
                <Link
                    href="/"
                    className="inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-2xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition duration-200 hover:bg-mist focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    Back to home
                </Link>
            </div>
        </section>
    );
}
