<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'patient_id', 'encounter_id', 'created_by_id', 'issued_by_id', 'cancelled_by_id', 'invoice_number', 'status', 'currency', 'subtotal', 'discount_total', 'adjustment_total', 'total', 'paid_amount', 'balance_due', 'notes', 'issued_at', 'paid_at', 'cancelled_at', 'cancel_reason'])]
class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'adjustment_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function issuedBy(): BelongsTo { return $this->belongsTo(User::class, 'issued_by_id'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by_id'); }
    public function lineItems(): HasMany { return $this->hasMany(InvoiceLineItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isIssued(): bool { return $this->status === self::STATUS_ISSUED; }
    public function isPartiallyPaid(): bool { return $this->status === self::STATUS_PARTIALLY_PAID; }
    public function isPaid(): bool { return $this->status === self::STATUS_PAID; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
}