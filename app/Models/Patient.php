<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['facility_id', 'user_id', 'medical_record_number', 'first_name', 'middle_name', 'last_name', 'sex', 'date_of_birth', 'national_id', 'phone', 'email', 'address_line1', 'address_line2', 'city', 'district', 'country', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone', 'status', 'registered_at', 'portal_invited_at', 'portal_activated_at', 'portal_disabled_at'])]
class Patient extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DECEASED = 'deceased';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array { return ['date_of_birth' => 'date', 'registered_at' => 'datetime', 'portal_invited_at' => 'datetime', 'portal_activated_at' => 'datetime', 'portal_disabled_at' => 'datetime']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function clinicalEncounters(): HasMany { return $this->hasMany(ClinicalEncounter::class); }
    public function laboratoryOrders(): HasMany { return $this->hasMany(LaboratoryOrder::class); }

    public function getFullNameAttribute(): string { return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]))); }
    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function hasPortalAccount(): bool { return $this->user_id !== null; }
    public function hasActivePortal(): bool { return $this->user?->is_active === true && $this->portal_disabled_at === null; }
}
