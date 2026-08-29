<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryResult extends Model
{
    use HasFactory;

    protected $fillable = ['laboratory_order_item_id', 'recorded_by', 'result_value', 'unit', 'reference_range', 'is_abnormal', 'comments', 'recorded_at'];

    protected function casts(): array
    {
        return ['is_abnormal' => 'boolean', 'recorded_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LaboratoryOrderItem::class, 'laboratory_order_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
