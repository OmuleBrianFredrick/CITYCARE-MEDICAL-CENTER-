<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServicePoint> */
class ServicePointFactory extends Factory
{
    protected $model = ServicePoint::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('SP-???')),
            'type' => 'service',
            'location' => fake()->optional()->secondaryAddress(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
