<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'code', 'name', 'category', 'description', 'unit', 'is_active'])]
class BillableService extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function invoiceLineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
