<?php

namespace Tests\Unit\Services;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;
use App\Services\RegistrationFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationFeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function reunion(array $overrides = []): Reunion
    {
        return Reunion::factory()->create(array_merge([
            'registration_fee' => 500.00,
            'charge_type' => null,
            'charge_value' => null,
        ], $overrides));
    }

    public function test_total_with_no_charge_returns_base(): void
    {
        $reunion = $this->reunion();

        $this->assertSame('500.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_total_with_fixed_charge_adds_flat_amount(): void
    {
        $reunion = $this->reunion([
            'charge_type' => 'fixed',
            'charge_value' => 50.00,
        ]);

        $this->assertSame('550.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_total_with_percentage_charge_applies_to_subtotal(): void
    {
        $reunion = $this->reunion([
            'charge_type' => 'percentage',
            'charge_value' => 5.0,
        ]);

        $this->assertSame('525.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_total_with_percentage_charge_includes_guest_fees(): void
    {
        $reunion = $this->reunion([
            'registration_fee' => 500.00,
            'charge_type' => 'percentage',
            'charge_value' => 5.0,
        ]);

        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ]);
        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Child',
            'fee' => 150.00,
            'is_active' => true,
        ]);

        // subtotal = 500 + 2*300 + 1*150 = 1250.00
        // charge  = 1250 * 0.05 = 62.50
        // total   = 1312.50
        $this->assertSame(
            '1312.50',
            RegistrationFeeCalculator::total($reunion, adultCount: 2, childCount: 1)
        );
    }

    public function test_total_with_zero_charge_value_returns_base(): void
    {
        $reunion = $this->reunion([
            'charge_type' => 'fixed',
            'charge_value' => 0,
        ]);

        $this->assertSame('500.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_breakdown_returns_expected_shape_with_percentage_charge(): void
    {
        $reunion = $this->reunion([
            'registration_fee' => 500.00,
            'charge_type' => 'percentage',
            'charge_value' => 5.0,
        ]);

        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ]);

        $breakdown = RegistrationFeeCalculator::breakdown($reunion, adultCount: 2, childCount: 0);

        $this->assertSame(500.0, $breakdown['base']);
        $this->assertSame(300.0, $breakdown['adultFee']);
        $this->assertSame(0.0, $breakdown['childFee']);
        $this->assertSame(2, $breakdown['adultCount']);
        $this->assertSame(0, $breakdown['childCount']);
        $this->assertSame(['Adult' => 300.0], $breakdown['perHead']);
        $this->assertSame(1100.0, $breakdown['subtotal']);
        $this->assertSame('percentage', $breakdown['chargeType']);
        $this->assertSame('Service Charge', $breakdown['chargeLabel']);
        $this->assertSame(5.0, $breakdown['chargeValue']);
        $this->assertSame(55.0, $breakdown['chargeAmount']);
        $this->assertSame(1155.0, $breakdown['total']);
    }

    public function test_breakdown_with_no_charge_returns_zero_charge_amount(): void
    {
        $reunion = $this->reunion();

        $breakdown = RegistrationFeeCalculator::breakdown($reunion);

        $this->assertNull($breakdown['chargeType']);
        $this->assertSame(0.0, $breakdown['chargeValue']);
        $this->assertSame(0.0, $breakdown['chargeAmount']);
        $this->assertSame(500.0, $breakdown['total']);
    }
}
