<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationDispensingItem extends Model
{
    use HasFactory;

    protected $fillable = ['medication_dispensings_id', 'prescription_item_id', 'quantity_dispensed', 'batch_number', 'expiry_date'];

    protected $casts = ['quantity_dispensed' => 'decimal:3', 'expiry_date' => 'date'];

    public function dispensing(): BelongsTo { return $this->belongsTo(MedicationDispensing::class, 'medication_dispensings_id'); }
    public function prescriptionItem(): BelongsTo { return $this->belongsTo(PrescriptionItem::class); }
}
