<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'name', 'code', 'sku', 'category', 'unit', 'reorder_level', 'is_active'])]
class InventoryItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function stockBalances(): HasMany { return $this->hasMany(InventoryStockBalance::class); }
    public function stockMovements(): HasMany { return $this->hasMany(InventoryStockMovement::class); }
    public function purchaseOrderItems(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function goodsReceiptItems(): HasMany { return $this->hasMany(GoodsReceiptItem::class); }
}
