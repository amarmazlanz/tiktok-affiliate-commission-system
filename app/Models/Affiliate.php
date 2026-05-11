<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'upline_id',
        'name',
        'email',
        'phone',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upline_id');
    }

    public function directDownlines(): HasMany
    {
        return $this->hasMany(self::class, 'upline_id');
    }

    public function tiktokAccounts(): HasMany
    {
        return $this->hasMany(TiktokAccount::class);
    }

    public function tiktokOrders(): HasMany
    {
        return $this->hasMany(TiktokOrder::class);
    }

    public function receivedCommissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'receiver_affiliate_id');
    }

    public function sourcedCommissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'source_affiliate_id');
    }
}
