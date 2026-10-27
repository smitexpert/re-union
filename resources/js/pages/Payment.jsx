import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { formatBDT } from '@/components/registration/utils/format';
import PageLogoHeader from '@/components/PageLogoHeader';

function CopyButton({ value, label = 'Copy' }) {
    const [copied, setCopied] = useState(false);

    const onCopy = async () => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                const ta = document.createElement('textarea');
                ta.value = value;
                ta.setAttribute('readonly', '');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch (e) {
            // No-op: user can still long-press to select.
            console.error('Copy failed', e);
        }
    };

    return (
        <button
            type="button"
            onClick={onCopy}
            aria-label={`${label} ${value}`}
            className="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-pink-300 bg-white px-3 py-1.5 text-xs font-bold text-pink-700 transition hover:bg-pink-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 focus-visible:ring-offset-2"
        >
            {copied ? (
                <>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="h-4 w-4">
                        <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>Copied</span>
                </>
            ) : (
                <>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-4 w-4">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"
                        />
                    </svg>
                    <span>Copy</span>
                </>
            )}
        </button>
    );
}

const STATUS_BADGES = {
    paid: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-800',
    unpaid: 'bg-amber-100 text-amber-800',
    cancelled: 'bg-rose-100 text-rose-700',
    refunded: 'bg-sky-100 text-sky-700',
    failed: 'bg-rose-100 text-rose-700',
};

function Badge({ value }) {
    const cls = STATUS_BADGES[value] || 'bg-gray-100 text-gray-700';
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide ${cls}`}
        >
            {value}
        </span>
    );
}

/**
 * Fetch the logo from the public folder and convert it to a data URI so it
 * can be embedded directly into an SVG/canvas without CORS taint. Result is
 * cached on the module so repeated calls are free.
 */
let logoDataUriPromise = null;
function getLogoDataUri() {
    if (logoDataUriPromise) return logoDataUriPromise;
    logoDataUriPromise = fetch('/logo.png')
        .then((r) => {
            if (!r.ok) throw new Error(`logo fetch ${r.status}`);
            return r.blob();
        })
        .then(
            (blob) =>
                new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(reader.error);
                    reader.readAsDataURL(blob);
                }),
        )
        .catch((e) => {
            // Allow retry if the first attempt failed (e.g. offline then online).
            logoDataUriPromise = null;
            throw e;
        });
    return logoDataUriPromise;
}

/**
 * Render the downloadable reunion ticket to a canvas. Mirrors the visual
 * language of the on-screen TicketPreview: light paper card with a coral
 * ribbon, slate type, "Official" stamp, perforated gutter, and a tear-off
 * stub holding the code + segmented row-id counter.
 *
 * Portrait 1080x1350 (4:5) so the downloaded file matches the preview's
 * proportions and prints as a real-world ticket stub.
 */
async function renderTicketCanvas(registration, formatBDT, logoDataUri) {
    const W = 1080;
    const H = 1350;

    const logoImg = await new Promise((resolve, reject) => {
        const i = new Image();
        i.onload = () => resolve(i);
        i.onerror = (e) => reject(e);
        i.src = logoDataUri;
    });

    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');

    // --- Palette -----------------------------------------------------------
    const INK = '#0F172A';
    const LABEL = '#64748B';
    const STUB_BG = '#F8FAFC';
    const ACCENT = '#E11D48';
    const COUNTER_BG = '#0F172A';
    const COUNTER_DIGIT = '#FCD34D';

    // --- Page background (acts as the perforation "punch-through" color) ---
    ctx.fillStyle = '#F5F4F2';
    ctx.fillRect(0, 0, W, H);

    // --- Card geometry ----------------------------------------------------
    const cardX = W * 0.04;
    const cardY = H * 0.05;
    const cardW = W - cardX * 2;
    const cardH = H - cardY * 2;
    const cardR = 28;
    const padX = cardX + 56;

    // Drop shadow
    ctx.save();
    ctx.shadowColor = 'rgba(15,23,42,0.18)';
    ctx.shadowBlur = 28;
    ctx.shadowOffsetY = 8;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, cardX, cardY, cardW, cardH, cardR);
    ctx.fill();
    ctx.restore();

    // Card ring
    ctx.save();
    ctx.strokeStyle = '#E2E8F0';
    ctx.lineWidth = 1;
    roundRect(ctx, cardX + 0.5, cardY + 0.5, cardW - 1, cardH - 1, cardR - 0.5);
    ctx.stroke();
    ctx.restore();

    // --- Coral top ribbon (clipped to rounded top corners) ---------------
    ctx.save();
    ctx.beginPath();
    const ribbonH = 14;
    ctx.moveTo(cardX + cardR, cardY);
    ctx.lineTo(cardX + cardW - cardR, cardY);
    ctx.arcTo(cardX + cardW, cardY, cardX + cardW, cardY + cardR, cardR);
    ctx.lineTo(cardX + cardW, cardY + ribbonH);
    ctx.lineTo(cardX, cardY + ribbonH);
    ctx.lineTo(cardX, cardY + cardR);
    ctx.arcTo(cardX, cardY, cardX + cardR, cardY, cardR);
    ctx.closePath();
    ctx.fillStyle = ACCENT;
    ctx.fill();
    ctx.restore();

    // --- Helpers ----------------------------------------------------------
    const FONT_SANS = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    const FONT_MONO = 'ui-monospace, SFMono-Regular, Menlo, monospace';

    function drawText(text, opts) {
        const {
            x,
            y,
            size = 16,
            weight = 400,
            color = INK,
            font = FONT_SANS,
            align = 'left',
            baseline = 'top',
            letterSpacing = 0,
        } = opts;
        ctx.font = `${weight} ${size}px ${font}`;
        ctx.fillStyle = color;
        ctx.textAlign = align;
        ctx.textBaseline = baseline;
        if (letterSpacing > 0 && ctx.letterSpacing !== undefined) {
            ctx.letterSpacing = `${letterSpacing}px`;
        }
        ctx.fillText(String(text ?? ''), x, y);
        if (letterSpacing > 0 && ctx.letterSpacing !== undefined) {
            ctx.letterSpacing = '0px';
        }
    }

    // --- Header row -------------------------------------------------------
    const headerY = cardY + 56;
    const logoSize = 64;
    ctx.drawImage(logoImg, padX, headerY, logoSize, logoSize);

    drawText('SWAATC', {
        x: padX + logoSize + 18,
        y: headerY + 6,
        size: 18,
        weight: 600,
        color: LABEL,
        letterSpacing: 4.5,
        font: FONT_SANS,
    });
    drawText('Reunion Ticket', {
        x: padX + logoSize + 18,
        y: headerY + 32,
        size: 30,
        weight: 800,
        color: INK,
        font: FONT_SANS,
    });

    // Official stamp
    drawOfficialStamp(
        ctx,
        cardX + cardW - 56 - 200,
        headerY + 4,
        200,
        60,
        ACCENT,
    );

    // --- Attendee ---------------------------------------------------------
    const attendeeY = headerY + logoSize + 64;
    drawText('ATTENDEE', {
        x: padX,
        y: attendeeY,
        size: 18,
        weight: 600,
        color: LABEL,
        letterSpacing: 4,
    });
    drawText(String(registration.student_name ?? ''), {
        x: padX,
        y: attendeeY + 28,
        size: 48,
        weight: 800,
        color: INK,
    });

    // --- Meta grid --------------------------------------------------------
    const gridY = attendeeY + 110;
    const colW = (cardW - 56 * 2 - 24) / 2;

    const cells = buildMetaCells(registration);
    let cellIdx = 0;
    for (const cell of cells) {
        const col = cellIdx % 2;
        const row = Math.floor(cellIdx / 2);
        const cx = padX + col * (colW + 24);
        const cy = gridY + row * 78;
        drawText(cell.label.toUpperCase(), {
            x: cx,
            y: cy,
            size: 16,
            weight: 600,
            color: LABEL,
            letterSpacing: 2.5,
        });
        drawText(String(cell.value), {
            x: cx,
            y: cy + 26,
            size: 24,
            weight: 700,
            color: INK,
        });
        cellIdx++;
    }

    // --- Total Paid -------------------------------------------------------
    const totalY = gridY + Math.ceil(cells.length / 2) * 78 + 20;
    ctx.save();
    ctx.strokeStyle = '#E2E8F0';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padX, totalY);
    ctx.lineTo(cardX + cardW - 56, totalY);
    ctx.stroke();
    ctx.restore();
    drawText('TOTAL PAID', {
        x: padX,
        y: totalY + 16,
        size: 18,
        weight: 600,
        color: LABEL,
        letterSpacing: 4,
    });
    drawText(formatBDT(registration.total_fee), {
        x: cardX + cardW - 56,
        y: totalY + 8,
        size: 36,
        weight: 800,
        color: ACCENT,
        align: 'right',
    });

    // --- Horizontal perforation gutter ------------------------------------
    const perfY = totalY + 84;
    const perfLeftX = cardX + 56;
    const perfRightX = cardX + cardW - 56;

    // Notch circles (punch-through)
    ctx.save();
    ctx.fillStyle = '#F5F4F2';
    ctx.beginPath();
    ctx.arc(perfLeftX - 14, perfY, 14, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#E2E8F0';
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(perfRightX + 14, perfY, 14, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.restore();

    // Perforation dots
    ctx.save();
    ctx.fillStyle = '#CBD5E1';
    const dotR = 2.5;
    const dotGap = 14;
    for (let x = perfLeftX + 16; x < perfRightX - 16; x += dotGap) {
        ctx.beginPath();
        ctx.arc(x, perfY, dotR, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.restore();

    // --- Stub section (rounded bottom corners only) ----------------------
    const stubTop = perfY + 18;
    const stubBottom = cardY + cardH - 28;

    ctx.save();
    ctx.beginPath();
    ctx.moveTo(cardX, stubTop);
    ctx.lineTo(cardX + cardW, stubTop);
    ctx.lineTo(cardX + cardW, stubBottom - 28);
    ctx.arcTo(cardX + cardW, stubBottom, cardX + cardW - 28, stubBottom, 28);
    ctx.lineTo(cardX + 28, stubBottom);
    ctx.arcTo(cardX, stubBottom, cardX, stubBottom - 28, 28);
    ctx.lineTo(cardX, stubTop);
    ctx.closePath();
    ctx.fillStyle = STUB_BG;
    ctx.fill();
    ctx.clip();

    // Code label
    drawText('REGISTRATION CODE', {
        x: cardX + cardW / 2,
        y: stubTop + 40,
        size: 18,
        weight: 600,
        color: LABEL,
        letterSpacing: 4,
        align: 'center',
    });

    // Code value
    const codeText = String(registration.code ?? '');
    drawText(codeText, {
        x: cardX + cardW / 2,
        y: stubTop + 76,
        size: 56,
        weight: 800,
        color: INK,
        font: FONT_MONO,
        align: 'center',
        letterSpacing: 6,
    });

    // Sub-caption
    drawText('SINGLE ENTRY · PRESENT AT THE VENUE', {
        x: cardX + cardW / 2,
        y: stubTop + 160,
        size: 14,
        weight: 600,
        color: '#94A3B8',
        letterSpacing: 3.2,
        align: 'center',
    });

    // Segmented row-id counter
    if (registration.id != null) {
        drawSegmentedCounter(
            ctx,
            cardX + cardW / 2,
            stubTop + 210,
            String(registration.id),
            COUNTER_BG,
            COUNTER_DIGIT,
        );
    }
    ctx.restore();

    return canvas;
}

function buildMetaCells(registration) {
    const adultCount = Number(registration.guest_count_adult || 0);
    const childCount = Number(registration.guest_count_child || 0);
    const parts = [];
    if (adultCount > 0) parts.push(`${adultCount} Adult${adultCount === 1 ? '' : 's'}`);
    if (childCount > 0) parts.push(`${childCount} Child${childCount === 1 ? '' : 'ren'}`);
    const guestSummary = parts.length > 0 ? parts.join(', ') : 'No guests';

    const cells = [{ label: 'Reunion', value: registration.reunion_title }];
    if (registration.event_date) {
        cells.push({ label: 'Event Date', value: registration.event_date });
    }
    cells.push({ label: 'Guests', value: guestSummary });
    if (registration.tshirt_size) {
        cells.push({ label: 'T-Shirt', value: registration.tshirt_size });
    }
    return cells;
}

/**
 * Tilted double-border "Official" stamp using serif ink and a tiny shadow
 * for that hand-stamped feel.
 */
function drawOfficialStamp(ctx, x, y, w, h, color) {
    const FONT_SERIF = 'Georgia, "Times New Roman", serif';
    const cx = x + w / 2;
    const cy = y + h / 2;
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate((-8 * Math.PI) / 180);
    ctx.translate(-cx, -cy);

    ctx.lineWidth = 3;
    ctx.strokeStyle = color;
    roundRect(ctx, x, y, w, h, 6);
    ctx.stroke();
    ctx.lineWidth = 3;
    ctx.strokeStyle = '#ffffff';
    roundRect(ctx, x + 6, y + 6, w - 12, h - 12, 3);
    ctx.stroke();
    ctx.lineWidth = 2;
    ctx.strokeStyle = color;
    roundRect(ctx, x + 9, y + 9, w - 18, h - 18, 1);
    ctx.stroke();

    ctx.font = `900 22px ${FONT_SERIF}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = color;
    ctx.fillText('OFFICIAL', cx, cy + 1);
    ctx.fillStyle = 'rgba(225,29,72,0.35)';
    ctx.fillText('OFFICIAL', cx + 0.6, cy + 1.6);

    ctx.restore();
}

/**
 * Segmented amber-on-navy row-id counter — each digit in its own cell with
 * a thin slate separator. The id is zero-padded to 3 digits to match the
 * preview.
 */
function drawSegmentedCounter(ctx, cx, topY, idStr, bgColor, digitColor) {
    const FONT_MONO = 'ui-monospace, SFMono-Regular, Menlo, monospace';
    const padded = idStr.padStart(3, '0');
    const digits = padded.split('');
    const digitSize = 38;
    const cellW = 44;
    const cellH = digitSize + 14;
    const totalW = digits.length * cellW;
    const startX = cx - totalW / 2;

    ctx.save();
    ctx.fillStyle = bgColor;
    roundRect(ctx, startX, topY, totalW, cellH, 8);
    ctx.fill();
    ctx.restore();

    for (let i = 0; i < digits.length; i++) {
        const cellX = startX + i * cellW;
        if (i < digits.length - 1) {
            ctx.save();
            ctx.fillStyle = '#1E293B';
            ctx.fillRect(cellX + cellW - 1, topY + 4, 1, cellH - 8);
            ctx.restore();
        }
        ctx.save();
        ctx.font = `800 ${digitSize}px ${FONT_MONO}`;
        ctx.fillStyle = digitColor;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(digits[i], cellX + cellW / 2, topY + cellH / 2 + 1);
        ctx.restore();
    }
}

async function buildTicketPng(registration, formatBDT, logoDataUri) {
    // 1080x1350 (4:5 portrait) — matches the on-screen preview's proportions
    // so the downloaded file looks like the same card.
    const canvas = await renderTicketCanvas(registration, formatBDT, logoDataUri);
    const pngBlob = await new Promise((resolve, reject) =>
        canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob failed'))), 'image/png'),
    );
    return pngBlob;
}

// Canvas 2D rounded-rect helper (not supported in older Safari).
function roundRect(ctx, x, y, w, h, r) {
    const radius = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius);
    ctx.closePath();
}

const PAYMENT_NUMBER = '01735997767';

const PAYMENT_METHODS = {
    bkash: {
        key: 'bkash',
        label: 'bKash',
        title: 'কীভাবে বিকাশে টাকা পাঠাবেন',
        ussdCode: '*247#',
        ussdHeading: 'পদ্ধতি ১: *247# ডায়াল করে পাঠান',
        appHeading: 'পদ্ধতি ২: বিকাশ অ্যাপ থেকে পাঠান',
        recipientLabel: 'প্রাপকের বিকাশ পার্সোনাল নম্বর:',
        recipientShort: 'বিকাশ পার্সোনাল নম্বর',
        pinLabel: 'আপনার বিকাশ পিন নম্বর দিয়ে কনফার্ম করুন।',
        ussdStepsPrefix: 'আপনার মোবাইল ফোনের ডায়ালার থেকে',
        appIntro: 'আপনার মোবাইলে bKash অ্যাপটি ওপেন করুন (লগইন পিন দিয়ে)।',
        appNumberSuffix: '(বিকাশ পার্সোনাল নম্বর)।',
        pin: 'আপনার বিকাশ পিন',
        primaryClass: 'bg-pink-700',
        panelClass: 'rounded-2xl border-2 border-pink-300 bg-pink-50 p-5 shadow-md sm:p-6',
        titleClass: 'text-lg font-extrabold text-pink-900',
        panelTitleId: 'bkash-guide-heading',
        amountCardClass: 'rounded-xl border border-pink-300 bg-white/80 p-4',
        amountLabelClass: 'text-sm font-semibold text-pink-900',
        amountValueClass: 'font-mono text-3xl font-extrabold tracking-wide text-pink-700',
        recipientLabelClass: 'text-sm font-semibold text-pink-900',
        numberClass: 'font-mono text-2xl font-extrabold tracking-widest text-pink-700 select-all',
        hintClass: 'mt-1 text-xs text-pink-800',
        stepBoxClass: 'mt-4 rounded-xl border border-pink-200 bg-white/70 p-4',
        stepsClass: 'mt-3 list-decimal space-y-2 pl-5 text-sm text-pink-950 marker:font-bold marker:text-pink-700',
        warnClass: 'mt-5 rounded-xl bg-pink-700 px-4 py-3 text-sm font-semibold text-white shadow-sm',
    },
    nagad: {
        key: 'nagad',
        label: 'Nagad',
        title: 'কীভাবে নগদে টাকা পাঠাবেন',
        ussdCode: '*167#',
        ussdHeading: 'পদ্ধতি ১: *167# ডায়াল করে পাঠান',
        appHeading: 'পদ্ধতি ২: নগদ অ্যাপ থেকে পাঠান',
        recipientLabel: 'প্রাপকের নগদ পার্সোনাল নম্বর:',
        recipientShort: 'নগদ পার্সোনাল নম্বর',
        pinLabel: 'আপনার নগদ পিন নম্বর দিয়ে কনফার্ম করুন।',
        ussdStepsPrefix: 'আপনার মোবাইল ফোনের ডায়ালার থেকে',
        appIntro: 'আপনার মোবাইলে Nagad অ্যাপটি ওপেন করুন (লগইন পিন দিয়ে)।',
        appNumberSuffix: '(নগদ পার্সোনাল নম্বর)।',
        pin: 'আপনার নগদ পিন',
        primaryClass: 'bg-orange-600',
        panelClass: 'rounded-2xl border-2 border-orange-300 bg-orange-50 p-5 shadow-md sm:p-6',
        titleClass: 'text-lg font-extrabold text-orange-900',
        panelTitleId: 'nagad-guide-heading',
        amountCardClass: 'rounded-xl border border-orange-300 bg-white/80 p-4',
        amountLabelClass: 'text-sm font-semibold text-orange-900',
        amountValueClass: 'font-mono text-3xl font-extrabold tracking-wide text-orange-700',
        recipientLabelClass: 'text-sm font-semibold text-orange-900',
        numberClass: 'font-mono text-2xl font-extrabold tracking-widest text-orange-700 select-all',
        hintClass: 'mt-1 text-xs text-orange-800',
        stepBoxClass: 'mt-4 rounded-xl border border-orange-200 bg-white/70 p-4',
        stepsClass: 'mt-3 list-decimal space-y-2 pl-5 text-sm text-orange-950 marker:font-bold marker:text-orange-700',
        warnClass: 'mt-5 rounded-xl bg-orange-600 px-4 py-3 text-sm font-semibold text-white shadow-sm',
    },
};

function GuideSteps({ steps, className }) {
    return (
        <ol className={className}>
            {steps.map((step) => (
                <li key={step}>{step}</li>
            ))}
        </ol>
    );
}

/**
 * Tabbed "how to pay" panel. Two tabs (bKash / Nagad) controlled by
 * `activeKey`; clicking a tab swaps the body in place rather than rendering
 * both stacks back-to-back.
 */
function PaymentTabs({ total, activeKey, onChange }) {
    const totalText = formatBDT(total);
    const order = ['bkash', 'nagad'];

    return (
        <section
            aria-labelledby="payment-tabs-heading"
            className="rounded-2xl border border-gray-200 bg-white shadow-sm"
        >
            <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 sm:px-6">
                <h2 id="payment-tabs-heading" className="text-base font-bold text-gray-900">
                    কীভাবে পেমেন্ট করবেন
                </h2>
                <span className="text-xs text-gray-500">USSD বা অ্যাপ</span>
            </div>

            <div role="tablist" aria-label="Payment method" className="flex gap-1 px-2 pt-2 sm:px-3">
                {order.map((key) => {
                    const cfg = PAYMENT_METHODS[key];
                    const isActive = activeKey === key;
                    return (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            id={`payment-tab-${key}`}
                            aria-selected={isActive}
                            aria-controls={`payment-tabpanel-${key}`}
                            tabIndex={isActive ? 0 : -1}
                            onClick={() => onChange(key)}
                            className={`flex-1 rounded-t-xl px-4 py-2.5 text-sm font-extrabold uppercase tracking-wider transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2 ${
                                isActive
                                    ? `${cfg.primaryClass} text-white shadow-sm`
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {cfg.label}
                        </button>
                    );
                })}
            </div>

            {order.map((key) => (
                <PaymentGuidePanel
                    key={key}
                    cfg={PAYMENT_METHODS[key]}
                    total={total}
                    isActive={activeKey === key}
                />
            ))}
        </section>
    );
}

function PaymentGuidePanel({ cfg, total, isActive }) {
    const totalText = formatBDT(total);
    if (!isActive) {
        // Rendered once but hidden — keeps the DOM stable so Inertia-driven
        // re-renders don't drop copy-button state mid-copy.
        return (
            <div
                role="tabpanel"
                id={`payment-tabpanel-${cfg.key}`}
                aria-labelledby={`payment-tab-${cfg.key}`}
                hidden
                className="hidden"
            />
        );
    }

    const ussdSteps = [
        `${cfg.ussdStepsPrefix} ${cfg.ussdCode} ডায়াল করে কল/সেন্ড করুন।`,
        'মেনু থেকে "Send Money" অপশনটি সিলেক্ট করুন।',
        `${cfg.recipientLabel.replace(/:$/, '')} ${PAYMENT_NUMBER}।`,
        `টাকার পরিমাণ লিখুন: ${totalText} (আপনার মোট ফি)।`,
        cfg.pinLabel,
        'স্ক্রিনে Transaction ID দেখাবে — সেটা নিচের ফর্মে লিখুন।',
    ];

    const appSteps = [
        cfg.appIntro,
        'হোম স্ক্রিন থেকে "Send Money" বাটনে ট্যাপ করুন।',
        `প্রাপকের নম্বর হিসেবে লিখুন: ${PAYMENT_NUMBER} ${cfg.appNumberSuffix}`,
        `"Next" চাপুন, তারপর টাকার পরিমাণ লিখুন: ${totalText} (আপনার মোট ফি)।`,
        `"Send" বাটনে ট্যাপ করে ${cfg.pin} দিন।`,
        `কনফার্মেশন স্ক্রিন থেকে Transaction ID কপি করে নিচের ফর্মে লিখুন (মোট পাঠানো টাকা: ${totalText})।`,
    ];

    return (
        <div
            role="tabpanel"
            id={`payment-tabpanel-${cfg.key}`}
            aria-labelledby={`payment-tab-${cfg.key}`}
            className={cfg.panelClass + ' rounded-t-none border-0 shadow-none sm:p-6'}
        >
            <div className="flex items-start justify-between gap-3">
                <h3 id={cfg.panelTitleId} className={cfg.titleClass}>
                    {cfg.title}
                </h3>
                <span
                    className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-wider text-white ${cfg.primaryClass}`}
                >
                    {cfg.label}
                </span>
            </div>

            <div className={cfg.amountCardClass}>
                <p className={cfg.amountLabelClass}>আপনাকে যে পরিমাণ পাঠাতে হবে:</p>
                <p className={cfg.amountValueClass}>{totalText}</p>
                <p className={`mt-3 ${cfg.recipientLabelClass}`}>{cfg.recipientLabel}</p>
                <div className="mt-1 flex items-center gap-2">
                    <p className={cfg.numberClass}>{PAYMENT_NUMBER}</p>
                    <CopyButton value={PAYMENT_NUMBER} label={`Copy ${cfg.label} number`} />
                </div>
                <p className={cfg.hintClass}>
                    উপরের নম্বরে ঠিক {totalText} Send Money করুন — অন্য কোনো অপশন (Payment, Cash Out ইত্যাদি)
                    ব্যবহার করবেন না।
                </p>
            </div>

            <div className={cfg.stepBoxClass}>
                <div className="flex items-start justify-between gap-3">
                    <h4 className="text-base font-bold text-gray-900">{cfg.ussdHeading}</h4>
                    <span className="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-gray-700">
                        USSD
                    </span>
                </div>
                <GuideSteps steps={ussdSteps} className={cfg.stepsClass} />
            </div>

            <div className={cfg.stepBoxClass}>
                <div className="flex items-start justify-between gap-3">
                    <h4 className="text-base font-bold text-gray-900">{cfg.appHeading}</h4>
                    <span className="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-gray-700">
                        App
                    </span>
                </div>
                <GuideSteps steps={appSteps} className={cfg.stepsClass} />
            </div>

            <p className={cfg.warnClass}>
                <span aria-hidden="true">⚠️</span> গুরুত্বপূর্ণ: ঠিক {totalText} Send Money করুন, তারপর যে
                Transaction ID পাবেন এবং যে মোবাইল নম্বর থেকে পাঠিয়েছেন — দুটোই নিচের ফর্মে অবশ্যই লিখুন।
                Transaction ID ছাড়া পেমেন্ট যাচাই করা সম্ভব হবে না।
            </p>
        </div>
    );
}

/**
 * On-screen ticket preview. Light-paper "keepsake ticket" layout:
 *  - White card body with a thin slate border so it sits like a printed stub
 *    on the page background. No full-bleed gradient.
 *  - Coral (#E11D48) is the single accent — used for the perforation dots,
 *    the row-id pill, and the code's underline accent. Navy (#0F172A) anchors
 *    the type. Together they read as editorial/printed rather than app-card.
 *  - The registration code lives on its own panel inside a left "main" column
 *    with a tear-off stub column to its right (on `sm:` and up). The stub
 *    has notched top/bottom circles and a vertical perforation so the
 *    silhouette is unmistakably a ticket. On mobile they stack.
 */
function TicketPreview({ registration, formatBDT }) {
    const adultCount = Number(registration.guest_count_adult || 0);
    const childCount = Number(registration.guest_count_child || 0);
    const totalGuests = adultCount + childCount;
    const guestParts = [];
    if (adultCount > 0) guestParts.push(`${adultCount} Adult${adultCount === 1 ? '' : 's'}`);
    if (childCount > 0) guestParts.push(`${childCount} Child${childCount === 1 ? '' : 'ren'}`);
    const guestSummary = totalGuests > 0 ? guestParts.join(', ') : 'No guests';

    return (
        <div className="relative mt-4 overflow-hidden rounded-2xl bg-white text-slate-900 shadow-md ring-1 ring-slate-200">
            {/* Coral top ribbon — single accent color across the whole card */}
            <div
                aria-hidden="true"
                className="absolute inset-x-0 top-0 h-1.5 bg-rose-600"
            />

            {/* ── Header: brand + official pill ───────────────────── */}
            <div className="flex items-center justify-between gap-3 px-5 pb-2 pt-6 sm:px-6 sm:pt-7">
                <div className="flex items-center gap-3">
                    <img
                        src="/logo.png"
                        alt="SWAATC logo"
                        className="h-11 w-11 shrink-0 rounded-md ring-1 ring-slate-200"
                    />
                    <div className="leading-tight">
                        <div className="text-[10px] font-semibold tracking-[0.28em] text-slate-500">
                            SWAATC
                        </div>
                        <div className="text-base font-extrabold tracking-wide text-slate-900">
                            Reunion Ticket
                        </div>
                    </div>
                </div>
                <span
                    aria-label="Official ticket"
                    className="relative inline-flex shrink-0 select-none items-center justify-center px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-rose-700"
                    style={{
                        transform: 'rotate(-8deg)',
                        fontFamily: 'Georgia, "Times New Roman", serif',
                        border: '2px solid currentColor',
                        boxShadow: 'inset 0 0 0 2px white, inset 0 0 0 3px currentColor',
                        borderRadius: '3px',
                        textShadow: '0.5px 0.5px 0 currentColor',
                    }}
                >
                    Official
                </span>
            </div>

            {/* ── Attendee — focal name ───────────────────────────── */}
            <div className="px-5 sm:px-6">
                <div className="text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-500">
                    Attendee
                </div>
                <div className="mt-1 truncate text-xl font-extrabold leading-tight text-slate-900 sm:text-2xl">
                    {registration.student_name}
                </div>
            </div>

            {/* ── Meta as a 2-column grid ────────────────────────── */}
            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 px-5 text-xs sm:px-6">
                <div className="min-w-0">
                    <dt className="truncate font-semibold uppercase tracking-wider text-slate-500">
                        Reunion
                    </dt>
                    <dd className="mt-0.5 truncate font-bold text-slate-900">
                        {registration.reunion_title}
                    </dd>
                </div>
                {registration.event_date ? (
                    <div className="min-w-0">
                        <dt className="truncate font-semibold uppercase tracking-wider text-slate-500">
                            Event Date
                        </dt>
                        <dd className="mt-0.5 truncate font-bold text-slate-900">
                            {registration.event_date}
                        </dd>
                    </div>
                ) : null}
                <div className="min-w-0">
                    <dt className="truncate font-semibold uppercase tracking-wider text-slate-500">
                        Guests
                    </dt>
                    <dd className="mt-0.5 truncate font-bold text-slate-900">
                        {guestSummary}
                    </dd>
                </div>
                {registration.tshirt_size ? (
                    <div className="min-w-0">
                        <dt className="truncate font-semibold uppercase tracking-wider text-slate-500">
                            T-Shirt
                        </dt>
                        <dd className="mt-0.5 truncate font-bold text-slate-900">
                            {registration.tshirt_size}
                        </dd>
                    </div>
                ) : null}
            </dl>

            {/* ── Total paid — accent line ────────────────────────── */}
            <div className="mt-4 flex items-baseline justify-between border-t border-slate-200 px-5 pt-3 sm:px-6">
                <span className="text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-500">
                    Total Paid
                </span>
                <span className="text-base font-extrabold text-rose-700">
                    {formatBDT(registration.total_fee)}
                </span>
            </div>

            {/* ── Horizontal perforation gutter ──────────────────── */}
            <div
                aria-hidden="true"
                className="relative flex items-center justify-center gap-1.5 px-5 py-3 sm:px-6"
            >
                <span className="absolute left-0 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-mist ring-1 ring-slate-200" />
                <span className="absolute right-0 top-1/2 h-3 w-3 translate-x-1/2 -translate-y-1/2 rounded-full bg-mist ring-1 ring-slate-200" />
                {Array.from({ length: 18 }).map((_, i) => (
                    <span
                        key={i}
                        className="h-1 w-1 rounded-full bg-slate-300"
                    />
                ))}
            </div>

            {/* ── Stub section: code + row id ─────────────────────── */}
            <div className="flex flex-col items-center gap-2 bg-slate-50 px-5 py-5 sm:px-6">
                <div className="text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-500">
                    Registration Code
                </div>
                <div
                    className="w-full truncate text-center font-mono text-2xl font-extrabold tracking-[0.16em] text-slate-900 sm:text-[26px]"
                    style={{
                        fontFamily:
                            'ui-monospace, SFMono-Regular, Menlo, monospace',
                    }}
                    title={registration.code}
                >
                    {registration.code}
                </div>
                <div className="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                    Single entry · present at the venue
                </div>
                {registration.id != null ? (
                    <span
                        aria-label={`Registration row id ${registration.id}`}
                        className="mt-2 inline-flex items-stretch overflow-hidden rounded-md bg-slate-900 ring-1 ring-slate-900"
                        style={{
                            fontFamily:
                                'ui-monospace, SFMono-Regular, Menlo, monospace',
                        }}
                    >
                        {String(registration.id)
                            .padStart(3, '0')
                            .split('')
                            .map((digit, idx, arr) => (
                                <span
                                    key={`${digit}-${idx}`}
                                    className={`relative inline-flex h-7 min-w-[1.1ch] items-center justify-center px-1 text-sm font-extrabold text-amber-300 ${
                                        idx < arr.length - 1
                                            ? 'border-r border-slate-700'
                                            : ''
                                    }`}
                                >
                                    {digit}
                                </span>
                            ))}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

export default function Payment({ registration, latestPaymentStatus, showForm, flash }) {
    const [activePayMethod, setActivePayMethod] = useState('bkash');
    const [paying, setPaying] = useState(false);
    const [downloadingTicket, setDownloadingTicket] = useState(false);
    const [ticketStatus, setTicketStatus] = useState(null);

    const isTicketIssued =
        registration.registration_status === 'confirmed' &&
        registration.payment_status === 'paid';
    const isCompleted = latestPaymentStatus === 'completed';
    const isPending = latestPaymentStatus === 'pending';
    const canMakePayment = showForm && !isCompleted;

    const form = useForm({
        payment_method: 'bkash',
        amount: registration.total_fee,
        transaction_id: '',
        send_from_mobile_number: '',
        notes: '',
    });

    const setActivePayMethodSynced = (key) => {
        setActivePayMethod(key);
        form.setData('payment_method', key);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(`/payments/${registration.code}`, {
            preserveScroll: true,
        });
    };

    const downloadTicket = async () => {
        if (downloadingTicket) return;
        setDownloadingTicket(true);
        setTicketStatus(null);
        try {
            const logoDataUri = await getLogoDataUri();
            const png = await buildTicketPng(registration, formatBDT, logoDataUri);
            const url = URL.createObjectURL(png);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ticket-${registration.code}.png`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            setTicketStatus({ kind: 'success', message: 'টিকেট ডাউনলোড হয়েছে।' });
        } catch (err) {
            console.error('Ticket download failed', err);
            setTicketStatus({
                kind: 'error',
                message: 'টিকেট তৈরি করা যায়নি। আবার চেষ্টা করুন।',
            });
        } finally {
            setDownloadingTicket(false);
            window.setTimeout(() => setTicketStatus(null), 4000);
        }
    };

    return (
        <>
            <Head title={`Registration ${registration.code} — SWAATC`} />
            <PageLogoHeader />
            <main className="min-h-screen bg-mist px-4 py-8 sm:py-12">
                <div className="mx-auto max-w-md space-y-6">
                    {flash?.status ? (
                        <p
                            className="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                            role="status"
                        >
                            {flash.status}
                        </p>
                    ) : null}

                    <header className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h1 className="text-xl font-bold text-gray-900">Registration confirmed</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Thanks for registering. Your details are below — keep your registration code for future reference.
                        </p>
                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Code</dt>
                                <dd className="flex items-center gap-2">
                                    <span className="font-mono tracking-wide text-gray-900">{registration.code}</span>
                                    <CopyButton value={registration.code} label="Copy registration code" />
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Name</dt>
                                <dd className="text-right text-gray-900">{registration.student_name}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Reunion</dt>
                                <dd className="text-right text-gray-900">{registration.reunion_title}</dd>
                            </div>
                            {registration.event_date ? (
                                <div className="flex justify-between gap-3">
                                    <dt className="font-semibold text-gray-700">Event date</dt>
                                    <dd className="text-right text-gray-900">{registration.event_date}</dd>
                                </div>
                            ) : null}
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Total</dt>
                                <dd className="font-bold text-gray-900">{formatBDT(registration.total_fee)}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                                <dt className="font-semibold text-gray-700">Registration</dt>
                                <dd><Badge value={registration.registration_status} /></dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Payment</dt>
                                <dd><Badge value={registration.payment_status} /></dd>
                            </div>
                        </dl>
                    </header>

                    {canMakePayment && !paying ? (
                        <>
                            <PaymentTabs
                                total={registration.total_fee}
                                activeKey={activePayMethod}
                                onChange={setActivePayMethodSynced}
                            />
                            <button
                                type="button"
                                onClick={() => {
                                    form.setData('payment_method', activePayMethod);
                                    setPaying(true);
                                }}
                                className="inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                            >
                                Make Payment
                            </button>
                        </>
                    ) : null}

                    {canMakePayment && paying ? (
                        <form
                            onSubmit={submit}
                            className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6"
                        >
                            <h2 className="text-lg font-bold text-gray-900">Make a payment</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Send Money করার পর নিচের ফর্মে Transaction ID ও প্রেরকের মোবাইল নম্বর দিন।
                            </p>

                            <div className="mt-4 space-y-4">
                                <div>
                                    <label htmlFor="payment_method" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Method <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                                    </label>
                                    <select
                                        id="payment_method"
                                        required
                                        value={form.data.payment_method}
                                        onChange={(e) => form.setData('payment_method', e.target.value)}
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    >
                                        <option value="bkash">bKash Send Money</option>
                                        <option value="nagad">Nagad Send Money</option>
                                    </select>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Send Money করুন{' '}
                                        <span
                                            className={`font-mono font-bold ${
                                                form.data.payment_method === 'nagad'
                                                    ? 'text-orange-700'
                                                    : 'text-pink-700'
                                            }`}
                                        >
                                            {PAYMENT_NUMBER}
                                        </span>{' '}
                                        ({form.data.payment_method === 'nagad' ? 'Nagad' : 'bKash'} personal number).
                                    </p>
                                </div>
                                <div>
                                    <label htmlFor="amount" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Amount (BDT) <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                                    </label>
                                    <input
                                        id="amount"
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        inputMode="decimal"
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                    {form.errors.amount ? (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.amount}</p>
                                    ) : null}
                                </div>
                                <div>
                                    <label htmlFor="transaction_id" className="mb-1 block text-sm font-semibold text-gray-700">
                                        {form.data.payment_method === 'nagad' ? 'Nagad' : 'bKash'} Transaction ID <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                                    </label>
                                    <input
                                        id="transaction_id"
                                        type="text"
                                        required
                                        value={form.data.transaction_id}
                                        onChange={(e) => form.setData('transaction_id', e.target.value)}
                                        placeholder="e.g. 8N7KQ2X1YZ"
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                    {form.errors.transaction_id ? (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.transaction_id}</p>
                                    ) : null}
                                </div>
                                <div>
                                    <label htmlFor="send_from_mobile_number" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Sender mobile number (যে নম্বর থেকে পাঠিয়েছেন) <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                                    </label>
                                    <input
                                        id="send_from_mobile_number"
                                        type="tel"
                                        inputMode="tel"
                                        autoComplete="tel"
                                        required
                                        value={form.data.send_from_mobile_number}
                                        onChange={(e) => form.setData('send_from_mobile_number', e.target.value)}
                                        placeholder="01XXXXXXXXX"
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                    {form.errors.send_from_mobile_number ? (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.send_from_mobile_number}</p>
                                    ) : null}
                                </div>
                                <div>
                                    <label htmlFor="notes" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Notes (optional)
                                    </label>
                                    <textarea
                                        id="notes"
                                        rows={2}
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        className="block w-full rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing}
                                aria-busy={form.processing ? 'true' : 'false'}
                                className="mt-6 inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                            >
                                {form.processing ? 'Submitting…' : 'Submit payment'}
                            </button>
                        </form>
                    ) : null}

                    {isTicketIssued ? (
                        <section
                            aria-labelledby="ticket-heading"
                            className="rounded-2xl border border-cyan-200 bg-white p-5 shadow-sm sm:p-6"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 id="ticket-heading" className="text-lg font-bold text-gray-900">
                                        🎟️ আপনার রিইউনন টিকেট
                                    </h2>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Registration confirmed এবং Payment paid — আপনার টিকেট প্রস্তুত।
                                        নিচের বাটনে ক্লিক করে ছবি (PNG) ডাউনলোড করুন।
                                    </p>
                                </div>
                            </div>

                            <TicketPreview registration={registration} formatBDT={formatBDT} />

                            {ticketStatus ? (
                                <p
                                    role="status"
                                    aria-live="polite"
                                    className={`mt-3 rounded-xl px-3 py-2 text-xs ${
                                        ticketStatus.kind === 'success'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-rose-50 text-rose-700'
                                    }`}
                                >
                                    {ticketStatus.message}
                                </p>
                            ) : null}

                            <button
                                type="button"
                                onClick={downloadTicket}
                                disabled={downloadingTicket}
                                aria-busy={downloadingTicket ? 'true' : 'false'}
                                className="mt-4 inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"
                                    />
                                </svg>
                                {downloadingTicket ? 'টিকেট তৈরি হচ্ছে…' : 'টিকেট ডাউনলোড (PNG)'}
                            </button>
                        </section>
                    ) : null}
                </div>
            </main>
        </>
    );
}
