export default function TextAreaInput({ id, label, error, rows = 2, required, ...rest }) {
    const describedBy = error ? `${id}-error` : undefined;
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-gray-700">
                {label}{required ? <span aria-hidden="true" className="ml-0.5 text-red-600">*</span> : null}
            </label>
            <textarea
                id={id}
                name={id}
                rows={rows}
                aria-invalid={error ? 'true' : 'false'}
                aria-describedby={describedBy}
                aria-required={required ? 'true' : undefined}
                className={`block w-full rounded-xl border px-3 py-2.5 text-base text-gray-900 placeholder:text-gray-400 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                    error ? 'border-red-400' : 'border-gray-200'
                }`}
                {...rest}
            />
            {error ? (
                <p id={`${id}-error`} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
