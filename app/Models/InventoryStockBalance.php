<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'inventory_item_id', 'quantity_on_hand', 'quantity_reserved', 'quantity_available', 'status'])]
class InventoryStockBalance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'quantity_available' => 'decimal:3',
        ];
    }

    public function store(): BelongsTo { return $this->belongsTo(InventoryStore::class, 'store_id'); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
}
