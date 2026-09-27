<?php

namespace Tests\Feature;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegistrationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_passes_full_fee_breakdown_to_inertia(): void
    {
        $reunion = Reunion::factory()->withPercentageCharge(5.0)->create([
            'registration_fee' => 500.00,
        ]);

        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ]);

        $response = $this->get('/register');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Register')
            ->where('mode', 'open')
            ->where('feeBreakdown.base', 500)
            ->where('feeBreakdown.adultFee', 300)
            ->where('feeBreakdown.perHead.Adult', 300)
            ->where('feeBreakdown.subtotal', 500)
            ->where('feeBreakdown.chargeType', 'percentage')
            ->where('feeBreakdown.chargeLabel', 'Service Charge')
            ->where('feeBreakdown.chargeValue', 5)
            ->where('feeBreakdown.chargeAmount', 25)
            ->where('feeBreakdown.total', 525)
        );
    }

    public function test_show_with_no_charge_omits_charge_amount(): void
    {
        Reunion::factory()->create([
            'registration_fee' => 500.00,
            'charge_type' => null,
            'charge_value' => null,
        ]);

        $response = $this->get('/register');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Register')
            ->where('feeBreakdown.chargeType', null)
            ->where('feeBreakdown.chargeAmount', 0)
            ->where('feeBreakdown.total', 500)
        );
    }
}
