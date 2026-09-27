<?php

namespace App\Services;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;

class RegistrationFeeCalculator
{
    /**
     * Compute the total registration fee (decimal string) for a reunion
     * with the given adult and child guest counts.
     */
    public static function total(Reunion $reunion, int $adultCount = 0, int $childCount = 0): string
    {
        $base = (float) $reunion->registration_fee;

        $activeFees = ReunionGuestFee::query()
            ->where('reunion_id', $reunion->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('guest_type');

        $adultFee = $activeFees->get('Adult');
        $childFee = $activeFees->get('Child');

        $guestTotal = 0.0;
        if ($adultFee && $adultCount > 0) {
            $guestTotal += (float) $adultFee->fee * $adultCount;
        }
        if ($childFee && $childCount > 0) {
            $guestTotal += (float) $childFee->fee * $childCount;
        }

        return number_format($base + $guestTotal, 2, '.', '');
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
}
