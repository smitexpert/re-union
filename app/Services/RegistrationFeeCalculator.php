<?php

namespace App\Services;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;

class RegistrationFeeCalculator
{
    /**
     * Compute the total registration fee (decimal string) for a reunion,
     * including base fee, per-head guest fees, and the configured service
     * charge (fixed BDT amount or percentage of the subtotal).
     */
    public static function total(Reunion $reunion, int $adultCount = 0, int $childCount = 0): string
    {
        $subtotal = self::subtotal($reunion, $adultCount, $childCount);
        $charge = self::chargeAmount($reunion, $subtotal);

        return number_format($subtotal + $charge, 2, '.', '');
    }

    /**
     * Full fee breakdown for the registration wizard. All floats, two decimals
     * of precision by convention.
     *
     * @return array{
     *   base: float,
     *   adultFee: float,
     *   childFee: float,
     *   adultCount: int,
     *   childCount: int,
     *   perHead: array<string, float>,
     *   subtotal: float,
     *   chargeType: string|null,
     *   chargeLabel: string,
     *   chargeValue: float,
     *   chargeAmount: float,
     *   total: float,
     * }
     */
    public static function breakdown(Reunion $reunion, int $adultCount = 0, int $childCount = 0): array
    {
        $base = (float) $reunion->registration_fee;
        $perHead = self::perHeadFees($reunion);
        $adultFee = (float) ($perHead['Adult'] ?? 0.0);
        $childFee = (float) ($perHead['Child'] ?? 0.0);

        $subtotal = self::subtotal($reunion, $adultCount, $childCount);
        $chargeAmount = self::chargeAmount($reunion, $subtotal);

        return [
            'base' => $base,
            'adultFee' => $adultFee,
            'childFee' => $childFee,
            'adultCount' => $adultCount,
            'childCount' => $childCount,
            'perHead' => $perHead,
            'subtotal' => round($subtotal, 2),
            'chargeType' => $reunion->charge_type,
            'chargeLabel' => 'Service Charge',
            'chargeValue' => (float) ($reunion->charge_value ?? 0.0),
            'chargeAmount' => round($chargeAmount, 2),
            'total' => round($subtotal + $chargeAmount, 2),
        ];
    }

    /**
     * Per-head fee map for live preview in the wizard.
     *
     * @return array<string, float>
     */
    public static function perHeadFees(Reunion $reunion): array
    {
        return ReunionGuestFee::query()
            ->where('reunion_id', $reunion->id)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (ReunionGuestFee $fee) => [
                $fee->guest_type => (float) $fee->fee,
            ])
            ->all();
    }

    private static function subtotal(Reunion $reunion, int $adultCount, int $childCount): float
    {
        $base = (float) $reunion->registration_fee;
        $perHead = self::perHeadFees($reunion);

        $adultFee = (float) ($perHead['Adult'] ?? 0.0);
        $childFee = (float) ($perHead['Child'] ?? 0.0);

        return $base
            + ($adultFee * $adultCount)
            + ($childFee * $childCount);
    }

    private static function chargeAmount(Reunion $reunion, float $subtotal): float
    {
        if (! $reunion->isChargeEnabled()) {
            return 0.0;
        }

        $value = (float) $reunion->charge_value;

        return match ($reunion->charge_type) {
            'fixed' => $value,
            'percentage' => $subtotal * ($value / 100),
            default => 0.0,
        };
    }
}
