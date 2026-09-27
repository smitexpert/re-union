<?php

namespace Database\Factories;

use App\Models\Reunion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reunion>
 */
class ReunionFactory extends Factory
{
    protected $model = Reunion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Reunion ' . fake()->year(),
            'event_date' => fake()->dateTimeBetween('+30 days', '+365 days')->format('Y-m-d'),
            'registration_deadline' => fake()->dateTimeBetween('+1 day', '+29 days')->format('Y-m-d'),
            'is_active' => true,
            'registration_fee' => 500.00,
            'charge_type' => null,
            'charge_value' => null,
        ];
    }

    public function withFixedCharge(float $value = 50.00): static
    {
        return $this->state(fn () => [
            'charge_type' => 'fixed',
            'charge_value' => $value,
        ]);
    }

    public function withPercentageCharge(float $value = 5.0): static
    {
        return $this->state(fn () => [
            'charge_type' => 'percentage',
            'charge_value' => $value,
        ]);
    }
}