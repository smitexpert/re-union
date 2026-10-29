/**
 * Browser-side photo preparation for the registration form.
 *
 * Phone cameras produce 3-8 MB photos, which every upload limit in the stack
 * has to accommodate. Rather than asking users to shrink photos by hand, we
 * re-encode them here: the long edge is capped and the result is written as a
 * JPEG. A 5 MB phone photo lands at roughly 200-400 KB, which uploads
 * instantly on mobile data.
 */

/** Longest edge, in pixels, of the photo that gets uploaded. */
export const MAX_PHOTO_DIMENSION = 1600;

/** JPEG quality used when re-encoding the photo. */
export const PHOTO_JPEG_QUALITY = 0.85;

/** Photos at or below this size are uploaded untouched. */
export const COMPRESSION_THRESHOLD_BYTES = 400 * 1024;

/** Largest photo the server accepts, mirroring Laravel's `max:5120`. */
export const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

/** Largest source file we will even attempt to decode. */
export const MAX_SOURCE_BYTES = 25 * 1024 * 1024;

/** Types we know how to decode and re-encode. */
const RE_ENCODABLE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

function formatBytes(bytes) {
    if (bytes >= 1024 * 1024) {
        return `${Math.round(bytes / (1024 * 1024))} MB`;
    }

    return `${Math.round(bytes / 1024)} KB`;
}

/**
 * Decode the file into something drawable. ImageBitmap is preferred because
 * it decodes off the main thread and honours the EXIF orientation the phone
 * wrote; the <img> fallback is there for browsers without it.
 *
 * @param {File} file
 * @returns {Promise<ImageBitmap|HTMLImageElement|null>}
 */
async function decodeImage(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            // Browsers that do not understand the options bag still decode
            // the file without it.
            try {
                return await createImageBitmap(file);
            } catch {
                return null;
            }
        }
    }

    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        image.src = url;
    });
}

function dimensionsOf(image) {
    return {
        width: image.naturalWidth || image.width,
        height: image.naturalHeight || image.height,
    };
}

function toJpegFile(blob, sourceName) {
    const base = sourceName.replace(/\.[^./\\]+$/, '');

    return new File([blob], base ? `${base}.jpg` : 'photo.jpg', {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
}

/**
 * Prepare a picked photo for upload.
 *
 * @param {File} file
 * @returns {Promise<{file: File|null, error: string|null}>} The file to send,
 *   or an error message when the photo cannot be used.
 */
export async function preparePhoto(file) {
    if (!file) {
        return { file: null, error: null };
    }

    if (file.size > MAX_SOURCE_BYTES) {
        return {
            file: null,
            error: `That photo is too large (${formatBytes(MAX_SOURCE_BYTES)} maximum). Please choose a smaller one.`,
        };
    }

    if (!RE_ENCODABLE_TYPES.includes(file.type)) {
        // Not something we can re-encode; the server's `image` and `mimes`
        // rules report it properly.
        return { file, error: null };
    }

    if (file.size <= COMPRESSION_THRESHOLD_BYTES) {
        return { file, error: null };
    }

    const image = await decodeImage(file);
    if (!image) {
        // The browser could not decode it (HEIC, for instance). Send the
        // original and let the server reject it with a proper message.
        return { file, error: null };
    }

    const { width: sourceWidth, height: sourceHeight } = dimensionsOf(image);
    const scale = Math.min(1, MAX_PHOTO_DIMENSION / Math.max(sourceWidth, sourceHeight));
    const width = Math.max(1, Math.round(sourceWidth * scale));
    const height = Math.max(1, Math.round(sourceHeight * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    if (!context) {
        return { file, error: null };
    }

    // JPEG has no alpha channel, so transparent pixels would otherwise come
    // out black.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.drawImage(image, 0, 0, width, height);

    if (typeof image.close === 'function') {
        image.close();
    }

    const blob = await new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', PHOTO_JPEG_QUALITY);
    });

    if (!blob) {
        return { file, error: null };
    }

    const prepared = toJpegFile(blob, file.name);

    if (prepared.size > MAX_PHOTO_BYTES) {
        return {
            file: null,
            error: 'That photo could not be resized small enough. Please choose a smaller one.',
        };
    }

    return { file: prepared, error: null };
}
