import { useEffect, useId, useRef, useState } from 'react';
import { preparePhoto } from '@/components/registration/utils/image';

/**
 * File-picker field with a square preview. Works for the registration form's
 * student photo. `value` may be a File (newly picked) or null; once picked,
 * a local object URL is shown in the preview until the parent clears it.
 *
 * The picked photo is resized in the browser before it is handed to the
 * parent, so phone photos arrive small enough for the server's upload
 * limits. See utils/image.js.
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
    const [localError, setLocalError] = useState(null);
    const [processing, setProcessing] = useState(false);

    // Resizing is asynchronous, so a slow pick must not overwrite a newer
    // one. Each pick takes a number and only the latest one wins.
    const pickNumber = useRef(0);

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

    const handlePick = async (e) => {
        const file = e.target.files?.[0] ?? null;
        if (!file) {
            onChange(null);
            return;
        }

        const currentPick = ++pickNumber.current;
        setProcessing(true);

        try {
            const { file: prepared, error: prepareError } = await preparePhoto(file);

            if (currentPick !== pickNumber.current) {
                // A newer pick has been made in the meantime.
                return;
            }

            if (prepareError) {
                setLocalError(prepareError);
                onChange(null);
                // Let the user pick the same file again once they fix it.
                if (inputRef.current) inputRef.current.value = '';
                return;
            }

            setLocalError(null);
            onChange(prepared);
        } catch {
            // preparePhoto guards against its own failures, but a browser
            // that cannot give us a canvas should not leave the field stuck.
            if (currentPick === pickNumber.current) {
                setLocalError('That photo could not be read. Please choose a different one.');
                onChange(null);
            }
        } finally {
            if (currentPick === pickNumber.current) {
                setProcessing(false);
            }
        }
    };

    const handleClear = () => {
        pickNumber.current += 1;
        if (inputRef.current) inputRef.current.value = '';
        setLocalError(null);
        onChange(null);
    };

    const displayError = localError ?? error;

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
                        disabled={processing}
                        aria-required={required ? 'true' : undefined}
                        aria-invalid={displayError ? 'true' : 'false'}
                        aria-describedby={displayError ? `${fieldId}-error` : undefined}
                        className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-ink file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:brightness-110 focus:outline-none disabled:cursor-wait disabled:opacity-60"
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
                        <p className="text-xs text-gray-500">
                            {processing
                                ? 'Resizing photo…'
                                : 'PNG, JPG or WebP. Large photos are resized automatically.'}
                        </p>
                    )}
                </div>
            </div>

            {displayError ? (
                <p id={`${fieldId}-error`} className="mt-1 text-sm text-red-600">
                    {displayError}
                </p>
            ) : null}
        </div>
    );
}
