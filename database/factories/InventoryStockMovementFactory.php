<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'store_id' => InventoryStore::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'goods_receipt_item_id' => null,
            'performed_by_id' => User::factory(),
            'movement_type' => 'receipt',
            'quantity' => 1,
            'balance_after' => 1,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => null,
        ];
    }
}
