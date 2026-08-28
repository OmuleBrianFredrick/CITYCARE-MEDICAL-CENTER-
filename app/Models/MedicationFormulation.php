<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationFormulation extends Model
{
    use HasFactory;

    protected $fillable = ['medication_id', 'strength', 'unit', 'pack_size', 'sku', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
