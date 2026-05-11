<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'username',
        'username_normalized',
        'status',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
