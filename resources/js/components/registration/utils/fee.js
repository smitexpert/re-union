import { formatBDT } from './format';

/**
 * Compute the per-line fee breakdown (base, adult guests, children) for the
 * given guest counts. The server-side feeBreakdown is computed once with
 * counts of 0, so we always recompute on the client to reflect live input.
 *
 * @returns {Array<{key: 'base'|'adult'|'child', label: string, amount: number}>}
 */
export function computeFeeLines(feeBreakdown, adultCount, childCount) {
    const fb = feeBreakdown ?? {};
    const base = Number(fb.base ?? 0);
    const perHead = fb.perHead ?? {};
    const adultFee = Number(perHead.Adult ?? 0);
    const childFee = Number(perHead.Child ?? 0);

    return [
        {
            key: 'base',
            label: 'Registration fee',
            amount: base,
        },
        {
            key: 'adult',
            label: `Adult guests (${adultCount} × ${formatBDT(adultFee)})`,
            amount: adultFee * adultCount,
        },
        {
            key: 'child',
            label: `Children (${childCount} × ${formatBDT(childFee)})`,
            amount: childFee * childCount,
        },
    ];
}

/**
 * Compute the service charge amount for a given subtotal. Mirrors the
 * backend's RegistrationFeeCalculator::chargeAmount so the wizard's live
 * preview matches what the server will compute on submit.
 */
export function computeServiceCharge(feeBreakdown, subtotal) {
    const fb = feeBreakdown ?? {};
    const chargeType = fb.chargeType ?? null;
    const chargeValue = Number(fb.chargeValue ?? 0);

    if (chargeType === 'fixed') return chargeValue;
    if (chargeType === 'percentage') return subtotal * (chargeValue / 100);
    return 0;
}

/**
 * Render a service-charge label like "Service Charge (5%)" or
 * "Service Charge (flat)". Returns null when no charge is configured.
 */
export function serviceChargeLabel(feeBreakdown) {
    const fb = feeBreakdown ?? {};
    const chargeType = fb.chargeType ?? null;
    if (! chargeType) return null;

    const chargeValue = Number(fb.chargeValue ?? 0);

    return chargeType === 'percentage'
        ? `Service Charge (${chargeValue}%)`
        : 'Service Charge (flat)';
}
