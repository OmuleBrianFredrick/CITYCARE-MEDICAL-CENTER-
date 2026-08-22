<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicationDispensing extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['facility_id', 'prescription_id', 'patient_id', 'dispensed_by', 'dispensing_number', 'status', 'notes', 'dispensed_at'];

    protected $casts = ['dispensed_at' => 'datetime'];

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function dispenser(): BelongsTo { return $this->belongsTo(User::class, 'dispensed_by'); }
    public function items(): HasMany { return $this->hasMany(MedicationDispensingItem::class, 'medication_dispensings_id'); }
}
