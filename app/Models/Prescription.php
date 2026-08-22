<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    public const STATUS_PRESCRIBED = 'prescribed';
    public const STATUS_PARTIALLY_DISPENSED = 'partially_dispensed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['facility_id', 'patient_id', 'encounter_id', 'prescribed_by', 'prescription_number', 'status', 'notes', 'prescribed_at', 'completed_at', 'cancelled_at'];

    protected $casts = ['prescribed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class); }
    public function prescriber(): BelongsTo { return $this->belongsTo(User::class, 'prescribed_by'); }
    public function items(): HasMany { return $this->hasMany(PrescriptionItem::class); }
    public function dispensings(): HasMany { return $this->hasMany(MedicationDispensing::class); }

    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
}
