<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalReferralAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinical_referral_id', 'uploaded_by', 'disk', 'file_path', 'file_name', 'mime_type', 'file_size',
    ];

    protected $hidden = [
        'disk',
        'file_path',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(ClinicalReferral::class, 'clinical_referral_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
