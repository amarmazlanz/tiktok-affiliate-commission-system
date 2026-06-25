<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateApplication extends Model
{
    protected $fillable = [
        'application_reference',
        'referral_code',
        'referrer_affiliate_id',
        'proposed_upline_id',
        'proposed_group_name',
        'full_name',
        'normalized_name',
        'nric_encrypted',
        'nric_hash',
        'masked_nric',
        'phone',
        'normalized_phone',
        'email',
        'normalized_email',
        'tiktok_username',
        'normalized_tiktok_username',
        'additional_tiktok_username',
        'normalized_additional_tiktok_username',
        'notes',
        'status',
        'duplicate_status',
        'duplicate_notes',
        'submission_fingerprint',
        'consent_at',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected $hidden = [
        'nric_encrypted',
        'nric_hash',
        'submission_fingerprint',
    ];

    protected $casts = [
        'nric_encrypted' => 'encrypted',
        'consent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referrer_affiliate_id');
    }

    public function proposedUpline(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'proposed_upline_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
