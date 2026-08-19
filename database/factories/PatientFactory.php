<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::query()->first()?->id ?? Facility::factory(),
            'medical_record_number' => 'CCMC-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => fake()->randomElement(['female', 'male']),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 day')->format('Y-m-d'),
            'national_id' => fake()->unique()->bothify('CM##########'),
            'phone' => fake()->unique()->numerify('07########'),
            'email' => fake()->unique()->safeEmail(),
            'country' => 'Uganda',
            'status' => Patient::STATUS_ACTIVE,
            'registered_at' => now(),
        ];
    }
}
