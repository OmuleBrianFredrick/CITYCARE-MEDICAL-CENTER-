<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LaboratoryOrderItem extends Model
{
    use HasFactory;

    public const STATUS_ORDERED = 'ordered';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['laboratory_order_id', 'laboratory_test_id', 'status', 'notes', 'completed_at', 'cancelled_at'];

    protected function casts(): array { return ['completed_at' => 'datetime', 'cancelled_at' => 'datetime']; }

    public function order(): BelongsTo { return $this->belongsTo(LaboratoryOrder::class, 'laboratory_order_id'); }
    public function laboratoryTest(): BelongsTo { return $this->belongsTo(LaboratoryTest::class); }
    public function result(): HasOne { return $this->hasOne(LaboratoryResult::class); }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
}
