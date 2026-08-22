<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'billable_service_id', 'amount', 'currency', 'effective_from', 'effective_to', 'is_active', 'notes'])]
class ServicePrice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function billableService(): BelongsTo { return $this->belongsTo(BillableService::class); }
    public function charges(): HasMany { return $this->hasMany(Charge::class); }
    public function invoiceLineItems(): HasMany { return $this->hasMany(InvoiceLineItem::class); }

    public function isActive(): bool { return $this->is_active === true; }
}