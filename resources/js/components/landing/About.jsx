import Reveal from '@/components/landing/Reveal';

const HIGHLIGHTS = [
    {
        label: 'Community',
        icon: 'M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
    },
    {
        label: 'Service',
        icon: 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.175.557l1.285 5.385a.562.562 0 0 1-.82.61l-4.725-2.885a.563.563 0 0 0-.586 0L9.482 20.13a.562.562 0 0 1-.82-.61l1.285-5.386a.563.563 0 0 0-.175-.557l-4.204-3.602c-.38-.325-.177-.948.321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z',
    },
    {
        label: 'Friendship',
        icon: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.026-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.100 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
    },
];

export default function About() {
    return (
        <section id="about" className="scroll-mt-24 bg-mist px-6 py-16 sm:py-20">
            <Reveal className="mx-auto max-w-2xl text-center">
                <h2 className="text-xs font-bold tracking-[0.2em] text-brand-ink uppercase text-balance">
                    About the Association
                    </h2>
                <div className="mx-auto mt-5 space-y-4 font-serif text-base leading-relaxed text-gray-700 sm:text-lg">
                    <p>
                        The Social Work Alumni Association of Tejgaon College (SWAATC) unites
                        graduates of the Department of Social Work who share a lasting
                        commitment to community, service, and one another.
                    </p>
                    <p>
                        Our upcoming reunion marks a historic gathering — a day to reconnect with
                        batchmates, honour our teachers, and celebrate the department’s
                        legacy on the campus where it all began. [Placeholder copy — replace
                        with the approved text.]
                    </p>
                </div>

                <ul className="mt-8 flex flex-wrap items-center justify-center gap-2">
                    {HIGHLIGHTS.map(({ label, icon }) => (
                        <li
                            key={label}
                            className="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-mist px-4 py-1.5 text-sm font-semibold text-gray-700"
                        >
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className="h-4 w-4 text-brand-ink"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" d={icon} />
                            </svg>
                            {label}
                        </li>
                    ))}
                </ul>
            </Reveal>
        </section>
    );
}