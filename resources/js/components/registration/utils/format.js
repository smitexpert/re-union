export function formatBDT(value) {
    const n = Number(value ?? 0);
    return `৳${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
