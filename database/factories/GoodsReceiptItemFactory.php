<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity_received' => 1,
            'unit_cost' => 1000,
            'line_total' => 1000,
        ];
    }
}
