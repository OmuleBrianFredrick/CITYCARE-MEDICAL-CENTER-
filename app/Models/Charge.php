<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['facility_id', 'patient_id', 'encounter_id', 'billable_service_id', 'service_price_id', 'created_by_id', 'voided_by_id', 'status', 'description', 'quantity', 'unit_price', 'subtotal', 'discount_amount', 'adjustment_amount', 'total', 'currency', 'idempotency_key', 'voided_at', 'void_reason'])]
class Charge extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_VOIDED = 'voided';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class); }
    public function billableService(): BelongsTo { return $this->belongsTo(BillableService::class); }
    public function servicePrice(): BelongsTo { return $this->belongsTo(ServicePrice::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by_id'); }
    public function invoiceLineItem(): HasOne { return $this->hasOne(InvoiceLineItem::class); }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isInvoiced(): bool { return $this->status === self::STATUS_INVOICED; }
    public function isVoided(): bool { return $this->status === self::STATUS_VOIDED; }
}