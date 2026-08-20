<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'encounter_id', 'author_id', 'chief_complaint', 'history_of_present_illness',
        'medical_history', 'examination', 'assessment', 'diagnosis', 'treatment_plan',
        'follow_up_plan', 'referral_notes', 'finalized_at',
    ];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }
}
