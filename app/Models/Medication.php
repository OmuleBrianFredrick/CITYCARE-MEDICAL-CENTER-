<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    use HasFactory;

    protected $fillable = ['facility_id', 'name', 'generic_name', 'code', 'route', 'dosage_form', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function formulations(): HasMany
    {
        return $this->hasMany(MedicationFormulation::class);
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
