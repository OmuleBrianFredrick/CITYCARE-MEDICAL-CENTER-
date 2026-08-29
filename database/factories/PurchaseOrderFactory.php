<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'supplier_id' => InventorySupplier::factory(),
            'store_id' => InventoryStore::factory(),
            'created_by_id' => User::factory(),
            'order_number' => 'PO-'.$this->faker->unique()->numerify('########'),
            'status' => 'draft',
            'ordered_at' => null,
            'notes' => null,
            'subtotal' => 0,
            'total' => 0,
        ];
    }
}
