<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalVital extends Model
{
    use HasFactory;

    protected $fillable = [
        'encounter_id', 'recorded_by', 'temperature_c', 'pulse_bpm', 'respiratory_rate',
        'oxygen_saturation', 'systolic_bp', 'diastolic_bp', 'weight_kg', 'height_cm',
        'bmi', 'pain_score', 'capillary_glucose_mmol_l', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'temperature_c' => 'decimal:1',
            'oxygen_saturation' => 'decimal:1',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'bmi' => 'decimal:2',
            'capillary_glucose_mmol_l' => 'decimal:2',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
