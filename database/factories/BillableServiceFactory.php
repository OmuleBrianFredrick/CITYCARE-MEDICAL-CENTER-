<?php

namespace Database\Factories;

use App\Models\BillableService;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillableService> */
class BillableServiceFactory extends Factory
{
    protected $model = BillableService::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'code' => fake()->unique()->bothify('SRV-####'),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['consultation', 'diagnostic', 'procedure', 'pharmacy', 'other']),
            'description' => fake()->optional()->sentence(),
            'unit' => 'item',
            'is_active' => true,
        ];
    }
}