<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'supplier_id', 'store_id', 'created_by_id', 'order_number', 'status', 'ordered_at', 'notes', 'subtotal', 'total'])]
class PurchaseOrder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(InventorySupplier::class, 'supplier_id'); }
    public function store(): BelongsTo { return $this->belongsTo(InventoryStore::class, 'store_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function goodsReceipts(): HasMany { return $this->hasMany(GoodsReceipt::class); }
}
