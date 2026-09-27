function FacebookIcon() {
    return (
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" className="h-5 w-5">
            <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12Z" />
        </svg>
    );
}

export default function Footer() {
    return (
        <footer id="contact" className="scroll-mt-24 bg-black px-6 pt-14 pb-28 text-center text-white md:pb-14">
            <img
                src="/logo.png"
                alt="SWAATC logo"
                width="48"
                height="48"
                loading="lazy"
                className="mx-auto h-12 w-12 rounded-full"
            />
            <p className="mt-3 text-sm font-bold tracking-[0.2em] uppercase">SWAATC</p>

            <div className="mt-6 flex items-center justify-center gap-3">
                <a
                    href="https://facebook.com/groups/[swaatc-group-placeholder]"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="SWAATC Facebook group"
                    className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition duration-200 hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                >
                    <FacebookIcon />
                </a>
            </div>

            <p className="mt-6 font-serif text-sm text-gray-300">
                Questions? Email us at{' '}
                <a
                    href="mailto:hello@swaatc.org"
                    className="rounded text-brand underline-offset-4 transition duration-200 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                >
                    hello@swaatc.org
                </a>
            </p>
            <p className="mt-4 text-xs text-gray-500">
                © SWAATC — Social Work Alumni Association, Tejgaon College, Dhaka
            </p>
        </footer>
    );
}