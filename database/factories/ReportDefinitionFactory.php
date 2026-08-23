<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportDefinitionFactory extends Factory
{
    protected $model = ReportDefinition::class;

    public function definition(): array
    {
        return [
            'code' => 'report-'.$this->faker->unique()->slug(2),
            'name' => $this->faker->sentence(3),
            'category' => $this->faker->randomElement(['clinical', 'financial', 'inventory', 'pharmacy', 'laboratory', 'operational']),
            'description' => $this->faker->sentence(),
            'supported_filters' => ['facility', 'date_range'],
            'is_active' => true,
        ];
    }
}
