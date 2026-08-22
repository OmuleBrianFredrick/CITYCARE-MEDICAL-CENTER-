<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'user_type', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class); }
    public function staffProfile(): HasOne { return $this->hasOne(StaffProfile::class); }
    public function patientProfile(): HasOne { return $this->hasOne(Patient::class); }
    public function invitationsReceived(): HasMany { return $this->hasMany(EmployeeInvitation::class, 'user_id'); }
    public function invitationsSent(): HasMany { return $this->hasMany(EmployeeInvitation::class, 'invited_by'); }
    public function appointmentsAsProvider(): HasMany { return $this->hasMany(Appointment::class, 'provider_id'); }
    public function appointmentsCreated(): HasMany { return $this->hasMany(Appointment::class, 'created_by'); }
    public function chargesCreated(): HasMany { return $this->hasMany(Charge::class, 'created_by_id'); }
    public function chargesVoided(): HasMany { return $this->hasMany(Charge::class, 'voided_by_id'); }
    public function invoicesCreated(): HasMany { return $this->hasMany(Invoice::class, 'created_by_id'); }
    public function invoicesIssued(): HasMany { return $this->hasMany(Invoice::class, 'issued_by_id'); }
    public function invoicesCancelled(): HasMany { return $this->hasMany(Invoice::class, 'cancelled_by_id'); }
    public function paymentsReceived(): HasMany { return $this->hasMany(Payment::class, 'received_by_id'); }
    public function paymentsVoided(): HasMany { return $this->hasMany(Payment::class, 'voided_by_id'); }

    public function hasRole(string|array $roles): bool
    {
        return $this->roles()->whereIn('slug', (array) $roles)->exists();
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', fn ($query) => $query->where('slug', $permission))->exists();
    }

    public function isStaff(): bool { return $this->user_type === 'staff'; }
    public function isPatient(): bool { return $this->user_type === 'patient'; }
    public function isActive(): bool { return $this->is_active === true; }
}
