<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => '01712345678',
            'graduation_year' => '2015',
            'option' => 'Honors',
            'session' => '2011-12',
            'current_occupation' => null,
            'present_address' => null,
            'permanent_address' => null,
            'office_address' => null,
            'photo' => null,
        ];
    }
}
