<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'store_id', 'inventory_item_id', 'goods_receipt_item_id', 'performed_by_id', 'movement_type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'notes'])]
class InventoryStockMovement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function store(): BelongsTo { return $this->belongsTo(InventoryStore::class, 'store_id'); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function goodsReceiptItem(): BelongsTo { return $this->belongsTo(GoodsReceiptItem::class); }
    public function performedBy(): BelongsTo { return $this->belongsTo(User::class, 'performed_by_id'); }
}
