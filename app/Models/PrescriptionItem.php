<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    public const STATUS_PRESCRIBED = 'prescribed';
    public const STATUS_PARTIALLY_DISPENSED = 'partially_dispensed';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['prescription_id', 'medication_id', 'medication_formulation_id', 'quantity', 'dose', 'route', 'frequency', 'duration', 'instructions', 'status'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function medication(): BelongsTo { return $this->belongsTo(Medication::class); }
    public function formulation(): BelongsTo { return $this->belongsTo(MedicationFormulation::class, 'medication_formulation_id'); }
    public function dispensingItems() { return $this->hasMany(MedicationDispensingItem::class); }
}
