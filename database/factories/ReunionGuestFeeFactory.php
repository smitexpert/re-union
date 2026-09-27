<?php

namespace Database\Factories;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReunionGuestFee>
 */
class ReunionGuestFeeFactory extends Factory
{
    protected $model = ReunionGuestFee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reunion_id' => Reunion::factory(),
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ];
    }
}
