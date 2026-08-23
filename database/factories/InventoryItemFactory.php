<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'name' => $this->faker->words(2, true),
            'code' => 'ITEM-'.$this->faker->unique()->numerify('#####'),
            'sku' => 'SKU-'.$this->faker->unique()->numerify('######'),
            'category' => 'General',
            'unit' => 'unit',
            'reorder_level' => 10,
            'is_active' => true,
        ];
    }
}
