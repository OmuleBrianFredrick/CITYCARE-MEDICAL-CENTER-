<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'name', 'code', 'phone', 'email', 'address', 'is_active'])]
class InventorySupplier extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class, 'supplier_id'); }
}
