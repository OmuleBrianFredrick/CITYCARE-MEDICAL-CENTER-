<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalReferralAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(ClinicalReferral::class, 'referral_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
