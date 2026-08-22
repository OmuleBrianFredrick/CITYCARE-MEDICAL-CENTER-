<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'legal_name', 'registration_number', 'phone', 'email', 'website',
        'address_line1', 'address_line2', 'city', 'district', 'country', 'timezone',
        'currency', 'logo_path', 'primary_color', 'secondary_color', 'accent_color', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }
}
