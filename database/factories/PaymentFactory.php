<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\ReunionRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => ReunionRegistration::factory(),
            'amount' => 500.00,
            'payment_method' => 'bkash',
            'transaction_id' => fake()->unique()->uuid(),
            'send_from_mobile_number' => '01712345678',
            'status' => 'pending',
            'paid_at' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }
}
