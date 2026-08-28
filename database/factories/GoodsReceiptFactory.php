<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\InventoryStore;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'store_id' => InventoryStore::factory(),
            'received_by_id' => User::factory(),
            'receipt_number' => 'GR-'.$this->faker->unique()->numerify('########'),
            'status' => 'posted',
            'received_at' => now(),
            'notes' => null,
        ];
    }
}
