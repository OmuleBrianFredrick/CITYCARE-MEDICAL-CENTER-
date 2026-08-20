<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryOrder extends Model
{
    use HasFactory;

    public const STATUS_ORDERED = 'ordered';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['facility_id', 'patient_id', 'encounter_id', 'ordered_by', 'order_number', 'status', 'notes', 'ordered_at', 'completed_at', 'cancelled_at'];

    protected function casts(): array { return ['ordered_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'encounter_id'); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(User::class, 'ordered_by'); }
    public function items(): HasMany { return $this->hasMany(LaboratoryOrderItem::class); }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
}
