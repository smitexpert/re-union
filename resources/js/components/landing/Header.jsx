import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const LINKS = [
    { label: 'About', href: '#about' },
    { label: 'Contact', href: '#contact' },
];

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2';

export default function Header({ registerUrl, paymentLookupUrl }) {
    const [open, setOpen] = useState(false);
    const toggleRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
                toggleRef.current?.focus();
            }
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open]);

    return (
        <header className="sticky top-0 z-50 border-b border-gray-100 bg-white/85 backdrop-blur-md">
            <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-4 sm:px-6">
                <a
                    href="#top"
                    className={`-ml-2 flex items-center gap-2 rounded-lg p-2 ${focusRing}`}
                >
                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-ink">
                        <img src="/logo.png" alt="SWAATC logo" width="36" height="36" className="h-9 w-9" />
                    </span>
                    <span className="text-sm font-bold tracking-tight">SWAATC</span>
                </a>

                <nav aria-label="Main" className="hidden items-center gap-1 sm:flex">
                    {LINKS.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className={`rounded-lg px-3 py-2 text-sm font-semibold transition duration-200 hover:bg-mist ${focusRing}`}
                        >
                            {link.label}
                        </a>
                    ))}
                </nav>

                <div className="flex items-center gap-2">
                    <Link
                        href={registerUrl}
                        className={`hidden rounded-full bg-brand px-5 py-2 text-sm font-bold text-black transition duration-200 hover:brightness-95 active:scale-[0.98] sm:inline-flex ${focusRing}`}
                    >
                        Register
                    </Link>
                    <Link
                        href={paymentLookupUrl}
                        className={`hidden rounded-full border border-gray-200 px-5 py-2 text-sm font-semibold text-gray-700 transition duration-200 hover:bg-mist sm:inline-flex ${focusRing}`}
                    >
                        Find registration
                    </Link>
                    <button
                        ref={toggleRef}
                        type="button"
                        aria-label={open ? 'Close menu' : 'Open menu'}
                        aria-expanded={open}
                        aria-controls="mobile-menu"
                        onClick={() => setOpen((v) => !v)}
                        className={`flex h-10 w-10 flex-col items-center justify-center gap-1.5 rounded-lg transition duration-200 hover:bg-mist sm:hidden ${focusRing}`}
                    >
                        <span
                            className={`h-0.5 w-5 rounded-full bg-black transition duration-200 ${
                                open ? 'translate-y-2 rotate-45' : ''
                            }`}
                        />
                        <span
                            className={`h-0.5 w-5 rounded-full bg-black transition duration-200 ${
                                open ? 'opacity-0' : ''
                            }`}
                        />
                        <span
                            className={`h-0.5 w-5 rounded-full bg-black transition duration-200 ${
                                open ? '-translate-y-2 -rotate-45' : ''
                            }`}
                        />
                    </button>
                </div>
            </div>

            {open && (
                <nav
                    id="mobile-menu"
                    aria-label="Main"
                    className="animate-menu-in absolute inset-x-0 top-full border-b border-gray-100 bg-white/95 shadow-lg backdrop-blur-md"
                >
                    <ul className="mx-auto max-w-3xl space-y-1 px-4 py-3 sm:px-6">
                        {LINKS.map((link) => (
                            <li key={link.href}>
                                <a
                                    href={link.href}
                                    onClick={() => setOpen(false)}
                                    className={`block rounded-xl px-3 py-3 text-sm font-semibold transition duration-200 hover:bg-mist ${focusRing}`}
                                >
                                    {link.label}
                                </a>
                            </li>
                        ))}
                        <li className="pt-1 sm:hidden">
                            <Link
                                href={registerUrl}
                                onClick={() => setOpen(false)}
                                className={`block rounded-xl bg-brand px-3 py-3 text-center text-sm font-bold text-black transition duration-200 hover:brightness-95 ${focusRing}`}
                            >
                                Register Now
                            </Link>
                        </li>
                        <li className="sm:hidden">
                            <Link
                                href={paymentLookupUrl}
                                onClick={() => setOpen(false)}
                                className={`block rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-semibold text-gray-700 transition duration-200 hover:bg-mist ${focusRing}`}
                            >
                                Find registration
                            </Link>
                        </li>
                    </ul>
                </nav>
            )}
        </header>
    );
}