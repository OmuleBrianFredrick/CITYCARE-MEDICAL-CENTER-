<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'facility_id', 'department_id', 'service_point_id', 'patient_id', 'provider_id',
        'appointment_number', 'scheduled_start', 'scheduled_end', 'reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function servicePoint(): BelongsTo { return $this->belongsTo(ServicePoint::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isScheduled(): bool { return $this->status === self::STATUS_SCHEDULED; }
    public function isCheckedIn(): bool { return $this->status === self::STATUS_CHECKED_IN; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
}
