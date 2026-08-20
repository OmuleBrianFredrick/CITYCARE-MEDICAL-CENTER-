<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalEncounter extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_OUTPATIENT = 'outpatient';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_EMERGENCY = 'emergency';

    protected $fillable = [
        'facility_id', 'department_id', 'service_point_id', 'patient_id', 'appointment_id', 'clinician_id',
        'encounter_number', 'type', 'status', 'started_at', 'closed_at', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function servicePoint(): BelongsTo { return $this->belongsTo(ServicePoint::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(User::class, 'clinician_id'); }
    public function notes(): HasMany { return $this->hasMany(ClinicalNote::class, 'encounter_id'); }
    public function vitals(): HasMany { return $this->hasMany(ClinicalVital::class, 'encounter_id'); }
    public function diagnoses(): HasMany { return $this->hasMany(ClinicalDiagnosis::class, 'encounter_id'); }
    public function treatmentPlans(): HasMany { return $this->hasMany(ClinicalTreatmentPlan::class, 'encounter_id'); }
    public function referrals(): HasMany { return $this->hasMany(ClinicalReferral::class, 'encounter_id'); }

    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }
    public function isClosed(): bool { return $this->status === self::STATUS_CLOSED; }
}
