<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDefinition extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'category', 'description', 'supported_filters', 'is_active'];

    protected $casts = [
        'supported_filters' => 'array',
        'is_active' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }
}
