<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryTest extends Model
{
    use HasFactory;

    public const RESULT_TYPE_TEXT = 'text';
    public const RESULT_TYPE_NUMERIC = 'numeric';
    public const RESULT_TYPE_BOOLEAN = 'boolean';

    protected $fillable = ['facility_id', 'code', 'name', 'description', 'specimen_type', 'result_type', 'unit', 'reference_range', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function orderItems(): HasMany { return $this->hasMany(LaboratoryOrderItem::class); }
    public function isActive(): bool { return $this->is_active; }
}
