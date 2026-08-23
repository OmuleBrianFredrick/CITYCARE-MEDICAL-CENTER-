<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'service_point_id' => null,
            'name' => $this->faker->company().' Store',
            'code' => 'STORE-'.$this->faker->unique()->numerify('###'),
            'type' => 'store',
            'is_active' => true,
        ];
    }
}
