<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateBadge extends Model
{
    protected $fillable = ['affiliate_id', 'badge_key', 'earned_at', 'meta'];

    protected $casts = [
        'earned_at' => 'datetime',
        'meta' => 'array',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
