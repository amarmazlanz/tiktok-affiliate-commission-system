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
        'affiliate_code',
        'upline_id',
        'group_name',
        'affiliate_type',
        'name',
        'name_normalized',
        'email',
        'phone',
        'raw_upline_text',
        'raw_l1',
        'raw_l2',
        'raw_l3',
        'hierarchy_import_status',
        'hierarchy_import_remark',
        'raw_import_data',
        'status',
        'password_reset_at',
        'password_reset_by',
    ];

    protected $casts = [
        'password_reset_at' => 'datetime',
        'raw_import_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upline_id');
    }

    public function passwordResetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'password_reset_by');
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
