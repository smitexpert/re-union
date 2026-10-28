import { Link } from '@inertiajs/react';
import { useState } from 'react';
import Reveal from '@/components/landing/Reveal';

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2';

export default function Hero({ registerUrl, paymentLookupUrl }) {
    const [identifier, setIdentifier] = useState('');
    const handleLookup = (e) => {
        e.preventDefault();
        const value = String(identifier || '').trim();
        if (!value) return;
        const normalized = value.replace(/\s+/g, '').toUpperCase();
        window.location.href = `${paymentLookupUrl}/${encodeURIComponent(normalized)}`;
    };

    return (
        <section id="top" className="relative overflow-hidden px-6 pt-14 pb-20 text-center sm:pt-20 sm:pb-24">
            {/* Decorative brand wash */}
            <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-full">
                <div className="absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(0,191,255,0.14),transparent)]" />
                <div className="absolute -top-10 -left-24 h-56 w-56 rounded-full bg-brand/10 blur-3xl" />
                <div className="absolute top-32 -right-20 h-48 w-48 rounded-full bg-brand-ink/10 blur-3xl" />
            </div>

            <Reveal className="mx-auto max-w-2xl">
                <div className="mx-auto flex h-36 w-36 items-center justify-center rounded-full bg-radial from-brand to-brand-ink shadow-glow sm:h-44 sm:w-44">
                    <img
                        src="/logo.png"
                        alt="SWAATC — Social Work Alumni Association, Tejgaon College, Dhaka"
                        width="144"
                        height="144"
                        fetchPriority="high"
                        className="h-[85%] w-[85%]"
                    />
                </div>

                <p className="mt-8 inline-flex items-center gap-2 rounded-full border border-brand/30 bg-white/70 px-4 py-1.5 text-[11px] font-bold tracking-[0.2em] text-brand-ink uppercase">
                    <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-brand" />
                    SOCIAL WORK ALUMNI ASSOCIATION OF TEJGAON COLLEGE
                </p>

                <h1 className="mt-5 text-4xl font-bold leading-tight tracking-tight text-balance sm:text-5xl">
                    Reconnect | Reflect | Rejoice{' '}
                    <span className="text-brand-ink">SWAATC Reunion</span>
                </h1>

                <p className="mx-auto mt-5 max-w-xl font-serif text-lg leading-relaxed text-gray-600 sm:text-xl">
                    Join your fellow Tejgaon College Social Work alumni for a historic
                    gathering.
                </p>

                <div className="mt-10 flex flex-col items-center gap-4">
                    <Link
                        href={registerUrl}
                        className={`group inline-flex w-full max-w-sm items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-8 py-4 text-base font-bold text-black shadow-glow transition duration-200 hover:shadow-xl hover:shadow-brand/40 hover:brightness-105 active:scale-[0.98] ${focusRing}`}
                    >
                        Register Now
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            className="h-5 w-5 transition-transform duration-200 group-hover:translate-x-1"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                            />
                        </svg>
                    </Link>
                </div>

                <form
                    onSubmit={handleLookup}
                    aria-label="Find my registration"
                    className="mx-auto mt-8 w-full max-w-sm rounded-2xl border border-gray-200 bg-white/85 p-3 shadow-sm backdrop-blur"
                >
                    <label htmlFor="hero-lookup" className="block text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                        Already registered? Check status
                    </label>
                    <div className="mt-2 flex gap-2">
                        <input
                            id="hero-lookup"
                            type="text"
                            inputMode="text"
                            autoComplete="off"
                            required
                            value={identifier}
                            onChange={(e) => setIdentifier(e.target.value)}
                            placeholder="SWA-AB12CD34"
                            className={`block w-full min-h-11 rounded-xl border border-gray-200 px-3 py-2 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${focusRing}`}
                        />
                        <button
                            type="submit"
                            disabled={!identifier.trim()}
                            className={`inline-flex items-center justify-center gap-1.5 rounded-xl bg-gray-900 px-4 text-sm font-bold text-white transition duration-200 hover:bg-black disabled:cursor-not-allowed disabled:opacity-40 ${focusRing}`}
                        >
                            View status
                        </button>
                    </div>
                </form>
            </Reveal>
        </section>
    );
}
