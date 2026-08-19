<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Facility> */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        return [
            'name' => 'CityCare Medical Center',
            'legal_name' => 'CityCare Medical Center Limited',
            'registration_number' => 'CCMC-'.fake()->unique()->numerify('######'),
            'phone' => fake()->unique()->numerify('07########'),
            'email' => fake()->unique()->safeEmail(),
            'website' => 'https://citycare.test',
            'address_line1' => fake()->streetAddress(),
            'city' => 'Kampala',
            'district' => 'Kampala',
            'country' => 'Uganda',
            'timezone' => 'Africa/Kampala',
            'currency' => 'UGX',
            'primary_color' => '#2563EB',
            'secondary_color' => '#0F172A',
            'accent_color' => '#F4C430',
            'is_active' => true,
        ];
    }
}
