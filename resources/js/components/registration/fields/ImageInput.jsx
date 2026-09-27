import { useEffect, useId, useRef, useState } from 'react';

/**
 * File-picker field with a square preview. Works for the registration form's
 * student photo. `value` may be a File (newly picked) or null; once picked,
 * a local object URL is shown in the preview until the parent clears it.
 */
export default function ImageInput({
    id,
    label,
    value,
    onChange,
    error,
    accept = 'image/png,image/jpeg,image/webp',
    required = false,
    className = '',
}) {
    const reactId = useId();
    const fieldId = id || `image-input-${reactId}`;
    const inputRef = useRef(null);
    const [previewUrl, setPreviewUrl] = useState(null);

    // Keep the preview URL in sync with the value (covers clearing the file
    // after a successful submit, or replacing it with a different one).
    useEffect(() => {
        if (!value) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(value);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [value]);

    const handlePick = (e) => {
        const file = e.target.files?.[0] ?? null;
        onChange(file);
    };

    const handleClear = () => {
        if (inputRef.current) inputRef.current.value = '';
        onChange(null);
    };

    return (
        <div className={className}>
            <label htmlFor={fieldId} className="mb-1 block text-sm font-semibold text-gray-700">
                {label}
                {required ? (
                    <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                ) : null}
            </label>

            <div className="flex items-center gap-4">
                <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-mist">
                    {previewUrl ? (
                        <img
                            src={previewUrl}
                            alt="Selected preview"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div
                            aria-hidden="true"
                            className="flex h-full w-full items-center justify-center text-gray-400"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                className="h-7 w-7"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                />
                            </svg>
                        </div>
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-2">
                    <input
                        ref={inputRef}
                        id={fieldId}
                        type="file"
                        accept={accept}
                        onChange={handlePick}
                        required={required}
                        aria-required={required ? 'true' : undefined}
                        aria-invalid={error ? 'true' : 'false'}
                        aria-describedby={error ? `${fieldId}-error` : undefined}
                        className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-ink file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:brightness-110 focus:outline-none"
                    />
                    {value ? (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="self-start rounded-lg px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-mist focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            Remove
                        </button>
                    ) : (
                        <p className="text-xs text-gray-500">PNG, JPG or WebP up to 2 MB.</p>
                    )}
                </div>
            </div>

            {error ? (
                <p id={`${fieldId}-error`} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
