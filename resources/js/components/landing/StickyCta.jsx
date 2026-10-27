import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Fixed bottom "Register" bar for phones. Appears once the hero has
 * scrolled out of view, so the CTA is always reachable on a one-page site.
 */
export default function StickyCta({ registerUrl, paymentLookupUrl, observeId = 'top' }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const target = document.getElementById(observeId);
        if (!target || typeof IntersectionObserver === 'undefined') {
            return undefined;
        }
        const observer = new IntersectionObserver(([entry]) => setVisible(!entry.isIntersecting));
        observer.observe(target);
        return () => observer.disconnect();
    }, [observeId]);

    return (
        <div
            className={`fixed inset-x-0 bottom-0 z-40 border-t border-gray-100 bg-white/90 px-4 pt-3 pb-[calc(0.75rem_+_env(safe-area-inset-bottom))] backdrop-blur-md transition-[opacity,transform,visibility] duration-300 md:hidden ${
                visible
                    ? 'visible translate-y-0 opacity-100'
                    : 'invisible pointer-events-none translate-y-full opacity-0'
            }`}
        >
            <div className="flex items-center gap-2">
                <Link
                    href={registerUrl}
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-95 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    Register
                </Link>
                <Link
                    href={paymentLookupUrl}
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-base font-semibold text-gray-700 transition duration-200 hover:bg-mist focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    Find registration
                </Link>
            </div>
        </div>
    );
}