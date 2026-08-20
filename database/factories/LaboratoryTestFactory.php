<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryTest;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryTestFactory extends Factory
{
    protected $model = LaboratoryTest::class;

    public function definition(): array
    {
        return ['facility_id' => Facility::factory(), 'code' => strtoupper(fake()->unique()->bothify('LAB-###??')), 'name' => fake()->words(3, true), 'description' => fake()->sentence(), 'specimen_type' => 'Blood', 'result_type' => LaboratoryTest::RESULT_TYPE_TEXT, 'unit' => null, 'reference_range' => null, 'is_active' => true];
    }
}
