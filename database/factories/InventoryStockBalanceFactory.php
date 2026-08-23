<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryStore;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStockBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => InventoryStore::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'quantity_available' => 0,
            'status' => 'active',
        ];
    }
}
