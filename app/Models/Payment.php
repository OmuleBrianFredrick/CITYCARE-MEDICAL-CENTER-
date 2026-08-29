<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'received_by_id', 'voided_by_id', 'receipt_number', 'method', 'status', 'amount', 'currency', 'transaction_reference', 'notes', 'paid_at', 'voided_at', 'void_reason'])]
class Payment extends Model
{
    use HasFactory;

    public const METHOD_CASH = 'cash';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CARD = 'card';

    public const METHOD_INSURANCE = 'insurance';

    public const METHOD_OTHER = 'other';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_REFUNDED = 'refunded';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }
}
