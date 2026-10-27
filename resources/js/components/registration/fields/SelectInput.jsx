export default function SelectInput({
    id,
    label,
    error,
    options,
    placeholder,
    required,
    className = '',
    ...rest
}) {
    const describedBy = error ? `${id}-error` : undefined;
    return (
        <div className={className}>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-gray-700">
                {label}{required ? <span aria-hidden="true" className="ml-0.5 text-red-600">*</span> : null}
            </label>
            <select
                id={id}
                name={id}
                aria-invalid={error ? 'true' : 'false'}
                aria-describedby={describedBy}
                aria-required={required ? 'true' : undefined}
                className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                    error ? 'border-red-400' : 'border-gray-200'
                }`}
                {...rest}
            >
                {placeholder ? <option value="">{placeholder}</option> : null}
                {options.map((option) => (
                    <option key={option} value={option}>{option}</option>
                ))}
            </select>
            {error ? (
                <p id={`${id}-error`} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
