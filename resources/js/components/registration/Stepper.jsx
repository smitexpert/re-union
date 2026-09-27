const STEPS = ['Student', 'Reunion', 'Guests', 'Review'];

export default function Stepper({ current }) {
    const percent = ((current + 1) / STEPS.length) * 100;
    return (
        <div className="mb-6">
            <div className="mb-2 flex items-center justify-between text-xs font-semibold tracking-wide text-gray-500 uppercase">
                <span>Step {current + 1} of {STEPS.length}</span>
                <span className="hidden sm:inline">{STEPS[current]}</span>
            </div>
            <div
                role="progressbar"
                aria-valuenow={current + 1}
                aria-valuemin={1}
                aria-valuemax={STEPS.length}
                aria-label={`Step ${current + 1} of ${STEPS.length}`}
                className="h-1.5 w-full overflow-hidden rounded-full bg-gray-200"
            >
                <div
                    className="motion-safe:transition-all motion-safe:duration-300 h-full rounded-full bg-brand-ink"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <ol className="mt-3 hidden grid-cols-4 gap-2 text-center sm:grid">
                {STEPS.map((label, i) => (
                    <li
                        key={label}
                        className={`text-xs font-semibold ${i <= current ? 'text-brand-ink' : 'text-gray-400'}`}
                    >
                        {label}
                    </li>
                ))}
            </ol>
        </div>
    );
}
