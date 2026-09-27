import { Link } from '@inertiajs/react';

/**
 * Compact, branded page header used by focused flows (registration form,
 * payment status page, etc.). Sits above the main content and links back to
 * the home page so the user always has a way out.
 *
 * The layout uses the same `max-w-md` container as the pages it tops so the
 * header aligns with the form/status cards below.
 */
export default function PageLogoHeader() {
    return (
        <header className="border-b border-gray-100 bg-white">
            <div className="mx-auto flex max-w-md items-center justify-center px-4 py-4 sm:py-5">
                <Link
                    href="/"
                    aria-label="SWAATC — go to home"
                    className="inline-flex items-center gap-2.5 rounded-lg px-1 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-ink ring-1 ring-slate-900/10">
                        <img
                            src="/logo.png"
                            alt=""
                            aria-hidden="true"
                            width="36"
                            height="36"
                            className="h-9 w-9"
                        />
                    </span>
                    <span className="text-base font-extrabold tracking-tight text-slate-900">
                        SWAATC
                    </span>
                </Link>
            </div>
        </header>
    );
}
