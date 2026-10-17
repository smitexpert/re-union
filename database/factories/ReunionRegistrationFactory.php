<?php

namespace Database\Factories;

use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReunionRegistration>
 */
class ReunionRegistrationFactory extends Factory
{
    protected $model = ReunionRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reunion_id' => Reunion::factory(),
            'student_id' => Student::factory(),
            'tshirt_size' => 'L',
            'guest_count' => 0,
            'total_fee' => 500.00,
            'registration_status' => 'pending',
            'payment_status' => 'unpaid',
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'registration_status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'registration_status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);
    }
}