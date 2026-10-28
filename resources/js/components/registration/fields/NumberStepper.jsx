import { useEffect, useState } from 'react';

function clamp(value, min, max) {
    let result = Math.max(min, value);
    if (max !== undefined && max !== null) {
        result = Math.min(max, result);
    }
    return result;
}

/**
 * A controllable count field rendered as [ − ] [ value ] [ + ].
 *
 * The inner control is a plain text input instead of `type="number"`, so the
 * browser never draws its own spinners next to the +/- buttons. Typing digits
 * still works for jumping straight to a count.
 */
export default function NumberStepper({
    id,
    label,
    error,
    value,
    onChange,
    min = 0,
    max,
    required,
    className = '',
}) {
    const describedBy = error ? `${id}-error` : undefined;
    const current = clamp(Number(value ?? 0) || 0, min, max);

    // Keep the text the user actually typed so the field can be emptied
    // mid-edit without the value snapping back to "0" under the caret.
    const [draft, setDraft] = useState(() => String(value ?? 0));

    useEffect(() => {
        setDraft((typed) => (Number(typed) === Number(value ?? 0) ? typed : String(value ?? 0)));
    }, [value]);

    const commit = (next) => {
        setDraft(String(next));
        onChange(next);
    };

    const onType = (e) => {
        const digits = e.target.value.replace(/\D/g, '');
        setDraft(digits);
        onChange(clamp(Number(digits || 0), min, max));
    };

    const step = (delta) => commit(clamp(current + delta, min, max));

    const buttonClass =
        'inline-flex size-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 transition hover:border-brand-ink hover:bg-brand/10 hover:text-brand-ink disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2';

    return (
        <div className={className}>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-gray-700">
                {label}{required ? <span aria-hidden="true" className="ml-0.5 text-red-600">*</span> : null}
            </label>
            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    onClick={() => step(-1)}
                    disabled={current <= min}
                    aria-label={`Decrease ${label}`}
                    className={buttonClass}
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-5 w-5"
                    >
                        <path d="M5 12h14" />
                    </svg>
                </button>
                <input
                    id={id}
                    name={id}
                    type="text"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    value={draft}
                    onChange={onType}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={describedBy}
                    aria-required={required ? 'true' : undefined}
                    className={`block min-h-12 min-w-0 flex-1 rounded-xl border px-1 py-2.5 text-center text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                        error ? 'border-red-400' : 'border-gray-200'
                    }`}
                />
                <button
                    type="button"
                    onClick={() => step(1)}
                    disabled={max !== undefined && max !== null && current >= max}
                    aria-label={`Increase ${label}`}
                    className={buttonClass}
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-5 w-5"
                    >
                        <path d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
            </div>
            {error ? (
                <p id={`${id}-error`} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
