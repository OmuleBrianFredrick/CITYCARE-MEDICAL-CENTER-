<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'facility_id',
        'event_type',
        'action',
        'auditable_type',
        'auditable_id',
        'before_values',
        'after_values',
        'context',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
