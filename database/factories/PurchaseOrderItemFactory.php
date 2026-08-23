<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity_ordered' => 1,
            'unit_cost' => 1000,
            'line_total' => 1000,
        ];
    }
}
